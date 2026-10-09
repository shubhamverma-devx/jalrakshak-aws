<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\Zone;
use App\Services\SnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function __construct(private readonly SnsService $sns) {}

    /**
     * A citizen subscribes to flood alerts for their zone.
     *
     * Email goes straight onto the zone's Amazon SNS topic and works instantly.
     * Phone is stored but not used for SMS yet: sending SMS to Indian numbers
     * needs DLT registration, which is a production step, not a demo one.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zone_slug' => ['required', 'string', 'exists:zones,slug'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $zone = Zone::where('slug', $data['zone_slug'])->firstOrFail();

        $existing = Subscriber::where('zone_id', $zone->id)->where('email', $data['email'])->first();

        if ($existing) {
            // Re-subscribing refreshes the status: SNS returns the real ARN for
            // an address that has since confirmed, and sends no second email.
            $refreshed = $this->sns->subscribeEmail($zone, $existing->email);

            $existing->update([
                'phone' => $data['phone'] ?? $existing->phone,
                'sns_subscription_arn' => $refreshed['arn'] ?? $existing->sns_subscription_arn,
                'sns_status' => $refreshed['status'],
            ]);

            return response()->json([
                'data' => ['zone' => $zone->name, 'email' => $existing->email, 'status' => $existing->sns_status],
                'message' => $existing->sns_status === 'confirmed'
                    ? 'You are already subscribed to alerts for '.$zone->name.'.'
                    : 'Almost there. Check your inbox and confirm the subscription for '.$zone->name.'.',
                'sms_note' => $this->smsNote($data['phone'] ?? null),
            ]);
        }

        $result = $this->sns->subscribeEmail($zone, $data['email']);

        $subscriber = Subscriber::create([
            'zone_id' => $zone->id,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'sns_subscription_arn' => $result['arn'],
            'sns_status' => $result['status'],
        ]);

        return response()->json([
            'data' => ['zone' => $zone->name, 'email' => $subscriber->email, 'status' => $subscriber->sns_status],
            'message' => $result['note'],
            'sms_note' => $this->smsNote($data['phone'] ?? null),
        ], 201);
    }

    private function smsNote(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        return 'Number saved. SMS alerts to Indian numbers need TRAI DLT registration, '
            .'which is pending for production. Email alerts are live now.';
    }
}
