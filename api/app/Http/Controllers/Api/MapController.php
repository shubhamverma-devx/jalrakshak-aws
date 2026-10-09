<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Village;
use App\Services\MapStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MapController — per village inundation map, stored in Amazon S3.
 *
 * Officer uploads an image or GeoJSON from the dashboard. The bucket stays
 * private: the citizen page reads the object back through a presigned URL that
 * is generated fresh on every request.
 */
class MapController extends Controller
{
    public function __construct(private readonly MapStorage $maps) {}

    /** POST /api/officer/village/{id}/map */
    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'map' => ['required', 'file', 'max:10240', 'mimes:png,jpg,jpeg,webp,json,geojson,txt'],
        ]);

        $village = Village::findOrFail($id);
        $stored = $this->maps->store($village, $request->file('map'));

        return response()->json([
            'message' => $this->maps->usingS3()
                ? 'Inundation map uploaded to Amazon S3.'
                : 'Inundation map saved on the local disk (S3 is off in this environment).',
            'map' => [
                'village_id' => $village->id,
                'url' => $stored['url'],
                'updated_at' => $village->fresh()->inundation_map_updated_at?->toIso8601String(),
            ],
        ]);
    }
}
