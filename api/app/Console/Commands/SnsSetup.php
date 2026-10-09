<?php

namespace App\Console\Commands;

use App\Models\Village;
use App\Services\SnsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Har gaon ka Amazon SNS topic pehle se bana deta hai, taaki kisi ke subscribe karne se
 * pehle hi topics console mein dikhein. CreateTopic idempotent hai, isliye dobara chalana
 * safe hai.
 */
class SnsSetup extends Command
{
    protected $signature = 'jalrakshak:sns-setup {--district= : sirf is district ke gaon}';

    protected $description = 'Create an Amazon SNS topic for each village';

    public function handle(SnsService $sns): int
    {
        if (! $sns->enabled()) {
            $this->error('SNS band hai. Pehle .env mein SNS_ENABLED=true karo.');

            return self::FAILURE;
        }

        $query = Village::orderBy('district')->orderBy('name');

        if ($district = $this->option('district')) {
            $query->where('district', $district);
        }

        $villages = $query->get();
        $ok = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($villages->count());
        $bar->start();

        foreach ($villages as $village) {
            try {
                $sns->ensureTopic($village);
                $ok++;
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->warn("  {$village->name}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Topics ready: {$ok}".($failed ? ", failed: {$failed}" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
