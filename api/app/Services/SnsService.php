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
            // ReturnSubscriptionArn jaan-bujh ke NAHI bhejte. Uske saath SNS pending
            // subscription ka bhi asli ARN laut deta hai, aur tab "confirmed hua ya
            // nahi" ka koi farq hi nahi bachta. Bina uske, pending ka jawaab literal
            // string "pending confirmation" hota hai, aur confirmed ka asli ARN.
            $result = $this->client()->subscribe([
                'TopicArn' => $this->ensureTopic($village),
                'Protocol' => 'email',
                'Endpoint' => $email,
            ]);

            $arn = (string) $result->get('SubscriptionArn');
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
     * Is gaon ke topic pe kitne email sach mein confirmed hain, SNS se poocha hua.
     *
     * KYUN SNS se, apne database se nahi: confirmation ka click AWS pe hota hai, hamare
     * app pe nahi. Agar sirf apni row padhein to officer ko wo number dikhega jo humne
     * aakhri baar likha tha, na ki wo jo sach mein alert payega. Call fail ho to database
     * ka number wapas de dete hain, kyunki galat number bhi bina number se behtar hai.
     */
    private function confirmedCount(Village $village): int
    {
        $fromDb = Subscriber::where('village_id', $village->id)->confirmed()->count();

        if (! $village->sns_topic_arn) {
            return $fromDb;
        }

        try {
            $confirmed = 0;
            $token = null;

            do {
                $page = $this->client()->listSubscriptionsByTopic(array_filter([
                    'TopicArn' => $village->sns_topic_arn,
                    'NextToken' => $token,
                ]));

                foreach ($page->get('Subscriptions') ?? [] as $sub) {
                    if (str_starts_with((string) ($sub['SubscriptionArn'] ?? ''), 'arn:')) {
                        $confirmed++;
                    }
                }

                $token = $page->get('NextToken');
            } while ($token);

            return $confirmed;
        } catch (AwsException $e) {
            Log::warning('SNS subscriber count failed', ['village' => $village->id, 'error' => $e->getAwsErrorMessage()]);

            return $fromDb;
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

        if (! $village) {
            return $this->result(0, 0, 0, 'Village not found for this alert.');
        }

        $confirmed = $this->confirmedCount($village);

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
