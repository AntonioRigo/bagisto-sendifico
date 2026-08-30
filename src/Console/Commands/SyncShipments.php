<?php

namespace Tapstar\Sendifico\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Tapstar\Sendifico\Repositories\ShipmentRepository;
use Tapstar\Sendifico\Services\ShipmentService;

class SyncShipments extends Command
{
    /**
     * Minimum seconds between real sync runs, imposed by Sendifico's server limits.
     *
     * @var int
     */
    public const MIN_INTERVAL_SECONDS = 4 * 3600;

    /**
     * Cache key holding the last real run's timestamp.
     *
     * @var string
     */
    public const LAST_RUN_KEY = 'sendifico:sync:last-run';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendifico:sync {--force : Ignore the minimum interval between runs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the state of every non-terminal Sendifico shipment';

    /**
     * Execute the console command.
     */
    public function handle(ShipmentRepository $shipmentRepository, ShipmentService $shipmentService): int
    {
        $lastRun = Cache::get(self::LAST_RUN_KEY);

        if (
            ! $this->option('force')
            && $lastRun
            && (time() - (int) $lastRun) < self::MIN_INTERVAL_SECONDS
        ) {
            $this->info('Skipped: the mandatory 4h interval between syncs has not elapsed.');

            return self::SUCCESS;
        }

        Cache::forever(self::LAST_RUN_KEY, time());

        $checked = 0;

        $failed = 0;

        foreach ($shipmentRepository->findSyncable() as $row) {
            $checked++;

            if (! $shipmentService->refresh($row)->success) {
                $failed++;
            }
        }

        $this->info("Synced {$checked} shipments ({$failed} failed).");

        return self::SUCCESS;
    }
}
