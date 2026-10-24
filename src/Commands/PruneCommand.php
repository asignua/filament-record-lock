<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Commands;

use Asignua\FilamentRecordLock\Support\RecordLocker;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'record-lock:prune';

    protected $description = 'Delete expired record locks';

    public function handle(RecordLocker $locker): int
    {
        $deleted = $locker->pruneExpired();

        $this->components->info("Deleted {$deleted} expired record lock(s).");

        return self::SUCCESS;
    }
}
