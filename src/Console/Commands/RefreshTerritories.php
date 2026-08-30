<?php

namespace Tapstar\Sendifico\Console\Commands;

use Illuminate\Console\Command;
use Tapstar\Sendifico\Services\TerritoryService;

class RefreshTerritories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendifico:territories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download the Sendifico territory catalog into the local cache';

    /**
     * Execute the console command.
     */
    public function handle(TerritoryService $territoryService): int
    {
        try {
            $count = $territoryService->refresh();
        } catch (\Exception $e) {
            $this->error('Territory refresh failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cached {$count} territories.");

        return self::SUCCESS;
    }
}
