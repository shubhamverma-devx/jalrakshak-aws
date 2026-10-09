<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reading;
use App\Models\Zone;
use App\Services\MapStorage;
use App\Services\RiskEngine;
use App\Services\ZonePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function __construct(
        private readonly ZonePresenter $presenter,
        private readonly RiskEngine $risk,
        private readonly MapStorage $maps,
    ) {}

    /** Every monitored zone with its current reading and computed risk. */
    public function index(): JsonResponse
    {
        $zones = Zone::with('latestReading')
            ->withCount('subscribers')
            ->orderBy('name')
            ->get();

        $rows = $zones->map(fn (Zone $z) => $this->presenter->summary($z));

        return response()->json([
            'data' => $rows->values(),
            'summary' => [
                'zones' => $rows->count(),
                'by_level' => collect(RiskEngine::LEVELS)
                    ->values()
                    ->mapWithKeys(fn ($level) => [$level => $rows->where('risk_level', $level)->count()])
                    ->all(),
                'people_at_risk' => (int) $rows->whereIn('risk_level', [RiskEngine::WARNING, RiskEngine::SEVERE])->sum('population'),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /** One zone with its recent trend, map and alert history. */
    public function show(Zone $zone): JsonResponse
    {
        $zone->load('latestReading')->loadCount('subscribers');

        return response()->json(['data' => $this->presenter->detail($zone)]);
    }

    /**
     * Record a new reading for a zone. This is what makes the risk level move
     * during the demo, and is where a real rain gauge feed would post.
     */
    public function storeReading(Request $request, Zone $zone): JsonResponse
    {
        $data = $request->validate([
            'rainfall_mm' => ['required', 'numeric', 'min:0', 'max:2000'],
            'water_level_m' => ['required', 'numeric', 'min:0', 'max:500'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        $before = $this->risk->assess($zone);

        Reading::create([
            'zone_id' => $zone->id,
            'rainfall_mm' => $data['rainfall_mm'],
            'water_level_m' => $data['water_level_m'],
            'source' => $data['source'] ?? 'officer',
            'recorded_at' => now(),
        ]);

        $zone->unsetRelation('latestReading')->load('latestReading');
        $after = $this->risk->assess($zone);

        return response()->json([
            'data' => $this->presenter->detail($zone),
            'risk_changed' => $before['level'] !== $after['level'],
            'risk_before' => $before['level'],
            'risk_after' => $after['level'],
        ]);
    }

    /** Upload an inundation map (image or GeoJSON) for a zone into Amazon S3. */
    public function uploadMap(Request $request, Zone $zone): JsonResponse
    {
        $request->validate([
            'map' => ['required', 'file', 'max:10240', 'mimes:png,jpg,jpeg,webp,json,geojson,txt'],
        ]);

        $stored = $this->maps->store($zone, $request->file('map'));

        return response()->json([
            'data' => $this->presenter->detail($zone->fresh()),
            'stored' => $stored,
            'message' => $this->maps->usingS3()
                ? 'Inundation map uploaded to Amazon S3.'
                : 'Inundation map saved on the local disk (S3 is off in this environment).',
        ]);
    }
}
