<?php

namespace Database\Seeders;

use App\Models\Village;
use App\Services\MapStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Throwable;

/**
 * InundationMapSeeder — bundled demo maps ko Amazon S3 pe daalta hai.
 *
 * KYUN seeder mein: demo se pehle `migrate:fresh --seed` chalta hai, aur agar maps
 * haath se upload karne padein to wo har baar bhool jaane wali cheez hai. Ab fresh
 * environment, chahe wo deploy/ec2-setup.sh ka banaya hua ho, maps ke saath hi upar
 * aata hai.
 *
 * Files `database/seed-maps/<village-slug>.png` hain. Jis gaon ki file nahi hai uska
 * map nahi hota, aur citizen page wahan saaf likh deta hai ki abhi survey nahi aaya.
 *
 * Storage fail hone par ye seed ko nahi girata — AWS credentials ke bina bhi app upar
 * aana chahiye.
 */
class InundationMapSeeder extends Seeder
{
    public function run(): void
    {
        $maps = app(MapStorage::class);
        $dir = database_path('seed-maps');

        if (! is_dir($dir)) {
            return;
        }

        foreach (Village::orderBy('name')->get() as $village) {
            $source = $dir.'/'.Str::slug($village->name).'.png';

            if (! is_file($source)) {
                continue;
            }

            try {
                // Reseed purana path gira deta hai, isliye pehle folder saaf karte hain
                // warna bucket mein orphan files jamti rehti hain.
                $maps->clearVillage($village);
                $stored = $maps->storeFromPath($village, $source);

                $this->command?->getOutput()->writeln(
                    "  <fg=gray>map: {$village->name} -> {$stored['disk']}:{$stored['path']}</>"
                );
            } catch (Throwable $e) {
                $this->command?->warn("  {$village->name} ka map upload nahi hua: ".$e->getMessage());
            }
        }
    }
}
