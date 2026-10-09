<?php

namespace App\Services;

use App\Models\Subscriber;
use App\Models\Zone;
use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;

/**
 * Amazon SNS wiring.
 *
 * One SNS topic per zone. Citizens subscribe their email to their zone's topic,
 * and "Trigger alert" publishes one message that SNS fans out to every
 * confirmed subscriber of that zone.
 *
 * When SNS_ENABLED is false the methods return a simulated result instead of
 * throwing, so the app still runs end to end before AWS credentials exist.
 */
class SnsService
{
    private ?SnsClient $client = null;

    public function enabled(): bool
    {
        return (bool) config('jalrakshak.sns.enabled');
    }

    private function client(): SnsClient
    {
        if ($this->client) {
            return $this->client;
        }

        $config = [
            'version' => 'latest',
            'region' => config('jalrakshak.aws.region'),
        ];

        // Only pass explicit keys when they are set. Leaving them out lets the
        // SDK fall back to its default chain, which is how an IAM instance role
        // on EC2 works, with no credentials on the server at all.
        $key = config('jalrakshak.aws.key');
        $secret = config('jalrakshak.aws.secret');

        if ($key && $secret) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        return $this->client = new SnsClient($config);
    }

    public function topicName(Zone $zone): string
    {
        $prefix = config('jalrakshak.sns.topic_prefix');

        // SNS topic names allow letters, digits, hyphens and underscores only.
        return substr($prefix.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $zone->slug), 0, 256);
    }

    /**
     * Make sure the zone has an SNS topic, creating it if needed.
     * CreateTopic is idempotent, so calling this twice is safe.
     */
    public function ensureTopic(Zone $zone): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($zone->sns_topic_arn) {
            return $zone->sns_topic_arn;
        }

        $result = $this->client()->createTopic([
            'Name' => $this->topicName($zone),
            'Tags' => [
                ['Key' => 'project', 'Value' => 'jalrakshak-aws'],
                ['Key' => 'zone', 'Value' => $zone->slug],
            ],
        ]);

        $arn = $result->get('TopicArn');
        $zone->update(['sns_topic_arn' => $arn]);

        return $arn;
    }

    /**
     * Subscribe a citizen's email to their zone topic. SNS sends a one click
     * confirmation email, after which they receive every alert for that zone.
     *
     * @return array{status: string, arn: ?string, note: string}
     */
    public function subscribeEmail(Zone $zone, string $email): array
    {
        if (! $this->enabled()) {
            return [
                'status' => 'local',
                'arn' => null,
                'note' => 'Saved locally. Amazon SNS is turned off in this environment.',
            ];
        }

        try {
            $arn = $this->ensureTopic($zone);

            $result = $this->client()->subscribe([
                'TopicArn' => $arn,
                'Protocol' => 'email',
                'Endpoint' => $email,
                'ReturnSubscriptionArn' => true,
            ]);

            return [
                'status' => 'pending',
                'arn' => $result->get('SubscriptionArn'),
                'note' => 'Check your inbox and click "Confirm subscription" to start receiving alerts.',
            ];
        } catch (AwsException $e) {
            Log::error('SNS subscribe failed', ['zone' => $zone->slug, 'error' => $e->getAwsErrorMessage()]);

            return [
                'status' => 'failed',
                'arn' => null,
                'note' => 'Saved locally, but the alert subscription could not be created: '.$e->getAwsErrorMessage(),
            ];
        }
    }

    /**
     * Publish one alert to a zone topic. SNS delivers it to every confirmed
     * subscriber of that zone.
     *
     * @return array{status: string, message_id: ?string, note: string}
     */
    public function publishAlert(Zone $zone, string $subject, string $message): array
    {
        if (! $this->enabled()) {
            return [
                'status' => 'simulated',
                'message_id' => null,
                'note' => 'Amazon SNS is turned off in this environment, so the alert was recorded but not sent.',
            ];
        }

        try {
            $arn = $this->ensureTopic($zone);

            $result = $this->client()->publish([
                'TopicArn' => $arn,
                // SNS caps email subjects at 100 characters.
                'Subject' => substr($subject, 0, 100),
                'Message' => $message,
            ]);

            return [
                'status' => 'sent',
                'message_id' => $result->get('MessageId'),
                'note' => 'Published to Amazon SNS and fanned out to confirmed subscribers.',
            ];
        } catch (AwsException $e) {
            Log::error('SNS publish failed', ['zone' => $zone->slug, 'error' => $e->getAwsErrorMessage()]);

            return [
                'status' => 'failed',
                'message_id' => null,
                'note' => 'Amazon SNS rejected the publish: '.$e->getAwsErrorMessage(),
            ];
        }
    }

    /** How many subscribers of this zone are expected to receive an alert. */
    public function recipientCount(Zone $zone): int
    {
        return Subscriber::where('zone_id', $zone->id)->count();
    }
}
