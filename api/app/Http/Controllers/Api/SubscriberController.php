<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Models\Village;
use App\Services\SnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SubscriberController — citizen apne gaon ke alerts subscribe karta hai.
 *
 * Ye Android app ke device token wale raaste ke saath chalta hai, uski jagah nahi.
 * Email Amazon SNS topic pe seedha jaata hai aur turant kaam karta hai. Phone number
 * save hota hai par SMS abhi nahi jaata: Indian numbers ke liye TRAI DLT registration
 * chahiye, jo production ka kaam hai. UI ye saaf likhta hai, chhupata nahi.
 */
class SubscriberController extends Controller
{
    public function __construct(private readonly SnsService $sns) {}

    /** POST /api/subscribe */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'village_id' => ['required', 'integer', 'exists:villages,id'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $village = Village::findOrFail($data['village_id']);
        $existing = Subscriber::where('village_id', $village->id)->where('email', $data['email'])->first();

        // Re-subscribing refreshes the status: SNS returns the real ARN for an
        // address that has since confirmed, and sends no second email.
        $result = $this->sns->subscribeEmail($village, $data['email']);

        $subscriber = $existing ?: new Subscriber(['village_id' => $village->id, 'email' => $data['email']]);
        $subscriber->fill([
            'phone' => $data['phone'] ?? $subscriber->phone,
            'sns_subscription_arn' => $result['arn'] ?? $subscriber->sns_subscription_arn,
            'sns_status' => $result['status'],
        ])->save();

        return response()->json([
            'message' => $result['note'],
            'subscriber' => [
                'village' => ['id' => $village->id, 'name' => $village->name, 'district' => $village->district],
                'email' => $subscriber->email,
                'status' => $subscriber->sns_status,
            ],
            'sms_note' => $data['phone'] ?? null
                ? 'Number saved. SMS alerts to Indian numbers need TRAI DLT registration, which is pending for production. Email alerts are live now.'
                : null,
        ], $existing ? 200 : 201);
    }
}
