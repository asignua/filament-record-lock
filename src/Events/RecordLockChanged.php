<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Events;

use Asignua\FilamentRecordLock\Support\Realtime;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * "The lock of a record was released or taken over" - sent to the private channel of that record so a page that
 * waits (or lost the lock) asks the server right away instead of at its next heartbeat. Identifiers only: the page
 * re-reads the state from the server, so the lock rules - not the socket payload - decide what it does.
 *
 * Broadcast immediately (no queue: without a worker a queued event would silently never arrive). It is a separate
 * event from {@see RecordLockTakenOver}, so listeners of that one are unaffected.
 */
final class RecordLockChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use InteractsWithBroadcasting;

    /** Event name for Echo (the listener is `.filament-record-lock.changed`). */
    public const string NAME = 'filament-record-lock.changed';

    public const string REASON_RELEASED = 'released';

    public const string REASON_TAKEN_OVER = 'taken_over';

    /**
     * @param string          $type   morph class of the record
     * @param string          $key    the record's key
     * @param string          $reason REASON_*
     * @param int|string|null $holder id of the user who holds the lock now (null = nobody)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly string $reason,
        public readonly string|int|null $holder = null,
    ) {
        $this->broadcastVia(Realtime::connection());
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(Realtime::channelFor($this->type, $this->key))];
    }

    public function broadcastAs(): string
    {
        return self::NAME;
    }

    /**
     * @return array{type: string, key: string, reason: string, holder: int|string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'key' => $this->key,
            'reason' => $this->reason,
            'holder' => $this->holder,
        ];
    }
}
