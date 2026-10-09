<?php

namespace Database\Seeders;

use App\Models\Reading;
use App\Models\Zone;
use App\Services\MapStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Eight monitored zones along the Brahmaputra, Barak and Kopili in Assam.
 *
 * Warning and danger levels are the Central Water Commission style marks for
 * these gauge sites, rounded for the demo. The current readings are chosen to
 * show two zones at each risk level so the dashboard is readable on screen.
 */
class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Dibrugarh', 'district' => 'Dibrugarh', 'river' => 'Brahmaputra',
                'latitude' => 27.472800, 'longitude' => 94.912000, 'population' => 154000,
                'warning_level_m' => 104.00, 'danger_level_m' => 105.70,
                'rainfall_mm' => 21.4, 'water_level_m' => 102.10,
            ],
            [
                'name' => 'Neamatighat, Jorhat', 'district' => 'Jorhat', 'river' => 'Brahmaputra',
                'latitude' => 26.750900, 'longitude' => 94.203700, 'population' => 126000,
                'warning_level_m' => 84.40, 'danger_level_m' => 85.04,
                'rainfall_mm' => 71.8, 'water_level_m' => 83.80,
            ],
            [
                'name' => 'Tezpur', 'district' => 'Sonitpur', 'river' => 'Brahmaputra',
                'latitude' => 26.633800, 'longitude' => 92.800000, 'population' => 102000,
                'warning_level_m' => 64.50, 'danger_level_m' => 65.23,
                'rainfall_mm' => 131.2, 'water_level_m' => 63.05,
            ],
            [
                'name' => 'Pandu, Guwahati', 'district' => 'Kamrup Metropolitan', 'river' => 'Brahmaputra',
                'latitude' => 26.183300, 'longitude' => 91.733300, 'population' => 968000,
                'warning_level_m' => 48.70, 'danger_level_m' => 49.68,
                'rainfall_mm' => 42.6, 'water_level_m' => 48.95,
            ],
            [
                'name' => 'Goalpara', 'district' => 'Goalpara', 'river' => 'Brahmaputra',
                'latitude' => 26.166700, 'longitude' => 90.625000, 'population' => 88000,
                'warning_level_m' => 35.50, 'danger_level_m' => 36.00,
                'rainfall_mm' => 212.8, 'water_level_m' => 34.70,
            ],
            [
                'name' => 'Dhubri', 'district' => 'Dhubri', 'river' => 'Brahmaputra',
                'latitude' => 26.020000, 'longitude' => 89.980000, 'population' => 71000,
                'warning_level_m' => 27.60, 'danger_level_m' => 28.62,
                'rainfall_mm' => 58.3, 'water_level_m' => 28.84,
            ],
            [
                'name' => 'Annapurna Ghat, Silchar', 'district' => 'Cachar', 'river' => 'Barak',
                'latitude' => 24.833300, 'longitude' => 92.778900, 'population' => 229000,
                'warning_level_m' => 18.90, 'danger_level_m' => 19.83,
                'rainfall_mm' => 68.5, 'water_level_m' => 17.52,
            ],
            [
                'name' => 'Kampur, Nagaon', 'district' => 'Nagaon', 'river' => 'Kopili',
                'latitude' => 26.350000, 'longitude' => 92.670000, 'population' => 64000,
                'warning_level_m' => 59.00, 'danger_level_m' => 60.00,
                'rainfall_mm' => 14.9, 'water_level_m' => 55.20,
            ],
        ];

        foreach ($zones as $data) {
            $rainfall = $data['rainfall_mm'];
            $water = $data['water_level_m'];
            unset($data['rainfall_mm'], $data['water_level_m']);

            $zone = Zone::updateOrCreate(
                ['slug' => str()->slug($data['name'])],
                $data + ['slug' => str()->slug($data['name'])]
            );

            $zone->readings()->delete();
            $this->seedHistory($zone, $rainfall, $water);
            $this->seedMap($zone);
        }
    }

    /**
     * Publish the bundled demo inundation map for this zone, if there is one.
     *
     * This runs on every seed so a fresh demo environment, including the one
     * built by deploy/ec2-setup.sh, comes up with maps already in Amazon S3.
     * A storage failure is reported but never fails the seed, so the app still
     * comes up when AWS credentials are missing.
     */
    private function seedMap(Zone $zone): void
    {
        $source = database_path('seed-maps/'.$zone->slug.'.png');

        if (! is_file($source)) {
            return;
        }

        try {
            $maps = app(MapStorage::class);

            // A reseed drops the stored path, so clear the zone's folder first
            // and the bucket does not collect orphans.
            $maps->clearZone($zone);

            $stored = $maps->storeFromPath($zone, $source);
            $this->command?->getOutput()->writeln(
                "  <fg=gray>map for {$zone->name} -> {$stored['disk']}:{$stored['path']}</>"
            );
        } catch (Throwable $e) {
            $this->command?->warn("  could not publish the map for {$zone->name}: ".$e->getMessage());
        }
    }

    /**
     * Seven days of readings rising towards the current value, so the officer
     * dashboard has a trend to show and not just one number.
     */
    private function seedHistory(Zone $zone, float $rainfall, float $water): void
    {
        $days = 7;
        $startRain = max(2.0, $rainfall * 0.18);
        $startWater = $water - 1.6;
        $now = Carbon::now();

        for ($i = $days; $i >= 0; $i--) {
            $progress = ($days - $i) / $days;

            // Ease towards the current value, with a small repeatable wobble.
            $eased = $progress ** 1.6;
            $wobble = sin(($days - $i) * 1.3) * 0.04;

            $isNow = ($i === 0);

            Reading::create([
                'zone_id' => $zone->id,
                'rainfall_mm' => $isNow ? $rainfall : round(max(0, $startRain + ($rainfall - $startRain) * $eased) * (1 + $wobble), 1),
                'water_level_m' => $isNow ? $water : round($startWater + ($water - $startWater) * $eased, 2),
                'source' => 'seed',
                'recorded_at' => $now->copy()->subDays($i),
            ]);
        }
    }
}
