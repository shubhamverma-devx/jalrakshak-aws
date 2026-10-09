<?php

namespace App\Console\Commands;

use App\Models\Zone;
use App\Services\SnsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates the Amazon SNS topic for every zone up front, so the topics are
 * visible in the console before anyone subscribes. CreateTopic is idempotent,
 * so re-running this is safe.
 */
class SnsSetup extends Command
{
    protected $signature = 'jalrakshak:sns-setup';

    protected $description = 'Create an Amazon SNS topic for each monitored zone';

    public function handle(SnsService $sns): int
    {
        if (! $sns->enabled()) {
            $this->error('SNS is turned off. Set SNS_ENABLED=true in .env first.');

            return self::FAILURE;
        }

        $rows = [];

        foreach (Zone::orderBy('name')->get() as $zone) {
            try {
                $arn = $sns->ensureTopic($zone);
                $rows[] = [$zone->name, $sns->topicName($zone), $arn ? 'ok' : 'skipped'];
            } catch (Throwable $e) {
                $rows[] = [$zone->name, $sns->topicName($zone), 'failed: '.$e->getMessage()];
            }
        }

        $this->table(['Zone', 'Topic', 'Status'], $rows);

        return self::SUCCESS;
    }
}
