<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Zone;
use App\Services\RiskEngine;
use App\Services\SnsService;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(
        private readonly RiskEngine $risk,
        private readonly SnsService $sns,
        private readonly ZonePresenter $presenter,
    ) {}

    /** The alert log, newest first. */
    public function index(): JsonResponse
    {
        $alerts = Alert::with('zone:id,name,district,slug')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Alert $a) => [
                'id' => $a->id,
                'zone' => $a->zone?->name,
                'zone_slug' => $a->zone?->slug,
                'district' => $a->zone?->district,
                'risk_level' => $a->risk_level,
                'message' => $a->message,
                'recipients_count' => $a->recipients_count,
                'channel' => $a->channel,
                'delivery_status' => $a->delivery_status,
                'delivery_note' => $a->delivery_note,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $alerts]);
    }

    /**
     * Officer presses "Trigger alert" on a zone.
     *
     * One publish to the zone's Amazon SNS topic fans the warning out to every
     * confirmed subscriber of that zone.
     */
    public function trigger(Request $request, Zone $zone): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:400'],
        ]);

        $assessment = $this->risk->assess($zone);

        if (! $assessment['has_data']) {
            return response()->json([
                'message' => 'This zone has no reading yet, so there is nothing to warn about.',
            ], 422);
        }

        $message = $this->risk->alertMessage($zone, $assessment, $this->zoneUrl($zone));

        if ($request->filled('note')) {
            $message .= "\n\nOfficer note: ".$request->input('note');
        }

        $subject = sprintf('JalRakshak %s alert: %s', $assessment['level'], $zone->name);
        $recipients = $this->sns->recipientCount($zone);

        $result = $this->sns->publishAlert($zone, $subject, $message);

        $alert = Alert::create([
            'zone_id' => $zone->id,
            'risk_level' => $assessment['level'],
            'message' => $message,
            'recipients_count' => $recipients,
            'channel' => 'sns-email',
            'sns_message_id' => $result['message_id'],
            'delivery_status' => $result['status'],
            'delivery_note' => $result['note'],
            'triggered_by' => 'officer',
        ]);

        $status = $result['status'] === 'failed' ? 502 : 200;

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'zone' => $zone->name,
                'risk_level' => $alert->risk_level,
                'recipients_count' => $alert->recipients_count,
                'delivery_status' => $alert->delivery_status,
                'sns_message_id' => $alert->sns_message_id,
                'message' => $alert->message,
                'created_at' => $alert->created_at->toIso8601String(),
            ],
            'zone' => $this->presenter->summary($zone),
            'message' => $result['note'],
        ], $status);
    }

    /** The citizen page for this zone, which always shows a fresh map. */
    private function zoneUrl(Zone $zone): string
    {
        return rtrim(config('app.url'), '/').'/?zone='.$zone->slug;
    }
}
