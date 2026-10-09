<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Subscriber;
use App\Models\Village;
use Aws\Exception\AwsException;
use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;

/**
 * Amazon SNS: the officer's alert leaves the building.
 *
 * This replaces the Firebase path from the SIH build. The idea is the same, a
 * targeted alert to one village rather than a blanket district SMS, but the
 * delivery is now an SNS topic per village. A citizen subscribes their email on
 * the web page, with no app install, and one Publish fans the warning out to
 * everyone in that village.
 *
 * Nothing here throws. Delivery can fail for reasons an officer needs to see,
 * so every failure comes back as data and the dashboard shows it.
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
        // SDK fall back to its default chain, which is how the IAM instance
        // role on EC2 works, with no credentials on the server at all.
        $key = config('jalrakshak.aws.key');
        $secret = config('jalrakshak.aws.secret');

        if ($key && $secret) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        return $this->client = new SnsClient($config);
    }

    /** Make sure the village has a topic. CreateTopic is idempotent. */
    public function ensureTopic(Village $village): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($village->sns_topic_arn) {
            return $village->sns_topic_arn;
        }

        $arn = $this->client()->createTopic([
            'Name' => $village->snsTopicName(),
            'Tags' => [
                ['Key' => 'project', 'Value' => 'jalrakshak-aws'],
                ['Key' => 'district', 'Value' => $village->district],
            ],
        ])->get('TopicArn');

        $village->update(['sns_topic_arn' => $arn]);

        return $arn;
    }

    /**
     * Subscribe a citizen's email to their village topic.
     *
     * @return array{status: string, arn: ?string, note: string}
     */
    public function subscribeEmail(Village $village, string $email): array
    {
        if (! $this->enabled()) {
            return [
                'status' => 'local',
                'arn' => null,
                'note' => 'Saved locally. Amazon SNS is turned off in this environment.',
            ];
        }

        try {
            $result = $this->client()->subscribe([
                'TopicArn' => $this->ensureTopic($village),
                'Protocol' => 'email',
                'Endpoint' => $email,
                'ReturnSubscriptionArn' => true,
            ]);

            $arn = (string) $result->get('SubscriptionArn');

            // An address that already confirmed comes back with its real ARN
            // and gets no second email. An unconfirmed one comes back as the
            // literal string "pending confirmation".
            $confirmed = str_starts_with($arn, 'arn:');

            return [
                'status' => $confirmed ? 'confirmed' : 'pending',
                'arn' => $confirmed ? $arn : null,
                'note' => $confirmed
                    ? 'You are subscribed. Alerts for this village will reach this address.'
                    : 'Check your inbox and click "Confirm subscription" to start receiving alerts.',
            ];
        } catch (AwsException $e) {
            Log::error('SNS subscribe failed', ['village' => $village->id, 'error' => $e->getAwsErrorMessage()]);

            return [
                'status' => 'failed',
                'arn' => null,
                'note' => 'Saved locally, but the alert subscription could not be created: '.$e->getAwsErrorMessage(),
            ];
        }
    }

    /**
     * Publish an officer alert to its village topic.
     *
     * The return shape matches what the dashboard already expected from the
     * Firebase path, so the officer still sees the honest difference between
     * "nobody in this village has subscribed" and "sending failed".
     *
     * @return array{sent: int, devices: int, failed: int, error: ?string, channel: string, message_id: ?string}
     */
    public function sendForAlert(Alert $alert): array
    {
        $village = $alert->village ?? Village::find($alert->village_id);

        $confirmed = $village
            ? Subscriber::where('village_id', $village->id)->confirmed()->count()
            : 0;

        if (! $village) {
            return $this->result(0, 0, 0, 'Village not found for this alert.');
        }

        if (! $this->enabled()) {
            return $this->result($confirmed, 0, 0, 'Amazon SNS is turned off in this environment.');
        }

        if ($confirmed === 0) {
            return $this->result(0, 0, 0, 'Nobody in this village has confirmed an alert subscription yet.');
        }

        try {
            $result = $this->client()->publish([
                'TopicArn' => $this->ensureTopic($village),
                // SNS caps email subjects at 100 characters.
                'Subject' => substr(sprintf('JalRakshak alert: %s, %s', $village->name, $village->district), 0, 100),
                'Message' => $this->body($alert, $village),
            ]);

            return $this->result($confirmed, $confirmed, 0, null, $result->get('MessageId'));
        } catch (AwsException $e) {
            Log::error('SNS publish failed', ['village' => $village->id, 'error' => $e->getAwsErrorMessage()]);

            return $this->result($confirmed, 0, $confirmed, $e->getAwsErrorMessage());
        }
    }

    /** The email body. Hindi first, because that is what most people here read first. */
    private function body(Alert $alert, Village $village): string
    {
        $lines = [
            sprintf('JALRAKSHAK: %s, %s', $village->name, $village->district),
            '',
            $alert->message_hi,
            '',
            '---',
            '',
            $alert->message_en,
            '',
            'Live status: '.rtrim(config('app.url'), '/').'/?village='.$village->id,
            '',
            sprintf('Issued by %s, district control room. Do not reply to this message.', $alert->sent_by),
        ];

        return implode("\n", $lines);
    }

    /** @return array{sent: int, devices: int, failed: int, error: ?string, channel: string, message_id: ?string} */
    private function result(int $devices, int $sent, int $failed, ?string $error, ?string $messageId = null): array
    {
        return [
            'devices' => $devices,
            'sent' => $sent,
            'failed' => $failed,
            'error' => $error,
            'channel' => 'sns-email',
            'message_id' => $messageId,
        ];
    }
}
