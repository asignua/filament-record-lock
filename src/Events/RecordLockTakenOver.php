<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fired when a user takes a record over from the user who held its lock. Listen to it to notify the previous
 * holder by mail, database notification, Slack... (the holder's open page is told by its next heartbeat anyway).
 */
final class RecordLockTakenOver
{
    public function __construct(
        public readonly Model $record,
        public readonly Authenticatable|Model|null $previousHolder,
        public readonly Authenticatable $newHolder,
    ) {}
}
