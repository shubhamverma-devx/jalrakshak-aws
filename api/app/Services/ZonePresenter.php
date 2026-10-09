<?php

namespace App\Services;

use App\Models\Zone;

/** Turns a Zone plus its risk assessment into the JSON shape the frontend reads. */
class ZonePresenter
{
    public function __construct(
        private readonly RiskEngine $risk,
        private readonly MapStorage $maps,
    ) {}

    /** Row shape for the officer table and the map markers. */
    public function summary(Zone $zone): array
    {
        $assessment = $this->risk->assess($zone);
        $reading = $zone->latestReading;

        return [
            'id' => $zone->id,
            'slug' => $zone->slug,
            'name' => $zone->name,
            'district' => $zone->district,
            'river' => $zone->river,
            'latitude' => $zone->latitude,
            'longitude' => $zone->longitude,
            'population' => $zone->population,
            'warning_level_m' => $zone->warning_level_m,
            'danger_level_m' => $zone->danger_level_m,
            'rainfall_mm' => $assessment['rainfall_mm'],
            'water_level_m' => $assessment['water_level_m'],
            'risk_level' => $assessment['level'],
            'risk_score' => $assessment['score'],
            'reasons' => $assessment['reasons'],
            'advice' => $this->risk->advice($assessment['level']),
            'alertable' => $this->risk->isAlertable($assessment['level']),
            'has_data' => $assessment['has_data'],
            'reading_at' => $reading?->recorded_at?->toIso8601String(),
            'inundation_map_url' => $this->maps->urlFor($zone),
            'inundation_map_updated_at' => $zone->inundation_map_updated_at?->toIso8601String(),
            'subscribers_count' => $zone->subscribers_count ?? $zone->subscribers()->count(),
            'sns_topic' => $zone->sns_topic_arn,
        ];
    }

    /** Summary plus the recent trend and the last alerts for this zone. */
    public function detail(Zone $zone): array
    {
        return $this->summary($zone) + [
            'readings' => $zone->readings()
                ->orderByDesc('recorded_at')
                ->limit(14)
                ->get()
                ->sortBy('recorded_at')
                ->values()
                ->map(fn ($r) => [
                    'rainfall_mm' => $r->rainfall_mm,
                    'water_level_m' => $r->water_level_m,
                    'recorded_at' => $r->recorded_at->toIso8601String(),
                ])->all(),
            'recent_alerts' => $zone->alerts()
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'risk_level' => $a->risk_level,
                    'recipients_count' => $a->recipients_count,
                    'delivery_status' => $a->delivery_status,
                    'created_at' => $a->created_at->toIso8601String(),
                ])->all(),
        ];
    }
}
