<?php

namespace App\Services;

use App\Models\Zone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Amazon S3 storage for per zone inundation maps.
 *
 * Officers upload an image or a GeoJSON file per zone. The file goes to the
 * S3 bucket, and the citizen page reads it back through a presigned URL so the
 * bucket itself can stay private.
 */
class MapStorage
{
    /**
     * Minutes a presigned S3 URL stays valid. The URL is regenerated on every
     * read, so it only has to outlive the page view that uses it. Asking for
     * longer is misleading anyway: on EC2 the signature is made with instance
     * role credentials, which expire in hours whatever is requested here.
     */
    public const URL_TTL_MINUTES = 60;

    public function disk(): string
    {
        return config('jalrakshak.maps_disk', 's3');
    }

    public function usingS3(): bool
    {
        return $this->disk() === 's3';
    }

    /**
     * Put an uploaded map in storage and record it on the zone.
     *
     * @return array{path: string, url: ?string, disk: string}
     */
    public function store(Zone $zone, UploadedFile $file): array
    {
        return $this->put(
            $zone,
            $file->get(),
            strtolower($file->getClientOriginalExtension() ?: 'bin'),
            $file->getMimeType() ?: 'application/octet-stream',
        );
    }

    /**
     * Same, from a file already on disk. Used by the seeder to publish the
     * bundled demo maps.
     *
     * @return array{path: string, url: ?string, disk: string}
     */
    public function storeFromPath(Zone $zone, string $absolutePath): array
    {
        return $this->put(
            $zone,
            (string) file_get_contents($absolutePath),
            strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'bin'),
            mime_content_type($absolutePath) ?: 'application/octet-stream',
        );
    }

    /**
     * @return array{path: string, url: ?string, disk: string}
     */
    private function put(Zone $zone, string $contents, string $extension, string $mime): array
    {
        $filename = sprintf('%s-%s.%s', $zone->slug, now()->format('Ymd-His'), $extension);
        $path = 'inundation-maps/'.$zone->slug.'/'.$filename;

        Storage::disk($this->disk())->put($path, $contents, ['ContentType' => $mime]);

        // Delete the map this one replaces so the bucket does not grow forever.
        if ($zone->inundation_map_path && $zone->inundation_map_path !== $path) {
            $this->delete($zone->inundation_map_path);
        }

        $zone->update([
            'inundation_map_path' => $path,
            'inundation_map_updated_at' => now(),
        ]);

        return ['path' => $path, 'url' => $this->urlForPath($path), 'disk' => $this->disk()];
    }

    /**
     * A readable URL for a stored map. Presigned on S3, plain public URL on the
     * local disk. Returns null if the URL cannot be built.
     */
    public function urlForPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk($this->disk());

            if ($this->usingS3()) {
                return $disk->temporaryUrl($path, now()->addMinutes(self::URL_TTL_MINUTES));
            }

            return $disk->url($path);
        } catch (Throwable $e) {
            Log::warning('Could not build inundation map URL', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function urlFor(Zone $zone): ?string
    {
        return $this->urlForPath($zone->inundation_map_path);
    }

    /** Remove every stored map for a zone. Used when reseeding a demo environment. */
    public function clearZone(Zone $zone): void
    {
        try {
            Storage::disk($this->disk())->deleteDirectory('inundation-maps/'.$zone->slug);
        } catch (Throwable $e) {
            Log::warning('Could not clear inundation maps', ['zone' => $zone->slug, 'error' => $e->getMessage()]);
        }
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            Storage::disk($this->disk())->delete($path);
        } catch (Throwable $e) {
            Log::warning('Could not delete inundation map', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
