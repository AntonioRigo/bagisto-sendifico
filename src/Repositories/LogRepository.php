<?php

namespace Tapstar\Sendifico\Repositories;

use Webkul\Core\Eloquent\Repository;

class LogRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return 'Tapstar\Sendifico\Contracts\Log';
    }

    /**
     * Delete log entries older than the given number of days.
     */
    public function purgeOlderThan(int $days = 90): int
    {
        return $this->getModel()
            ->newQuery()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
