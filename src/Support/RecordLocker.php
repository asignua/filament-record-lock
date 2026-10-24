<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Support;

use Asignua\FilamentRecordLock\Events\RecordLockTakenOver;
use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The one place that reads and writes locks. A record has at most one row (unique index on the morph pair), and
 * every transition is a single conditional statement, so two users racing for the same record cannot both win.
 */
class RecordLocker
{
    /**
     * @return class-string<RecordLock>
     */
    public function lockModel(): string
    {
        return RecordLockPlugin::resolve()->getLockModel();
    }

    /**
     * The lock row of a record, active or not.
     */
    public function find(Model $record): ?RecordLock
    {
        $model = $this->lockModel();

        return $model::query()
            ->where('lockable_type', $record->getMorphClass())
            ->where('lockable_id', (string) $record->getKey())
            ->first();
    }

    /**
     * The ACTIVE lock of a record (whoever holds it), or null.
     */
    public function active(Model $record): ?RecordLock
    {
        $lock = $this->find($record);

        return $lock !== null && $lock->isActive() ? $lock : null;
    }

    /**
     * Like {@see self::active()}, but for DISPLAY: when the record came from a query with `withRecordLock()` the
     * eager-loaded relation answers and no query is run (a table asks this once per row).
     */
    public function activeForDisplay(Model $record): ?RecordLock
    {
        if ($record->relationLoaded('recordLock')) {
            $lock = $record->getRelation('recordLock');

            return $lock instanceof RecordLock && $lock->isActive() ? $lock : null;
        }

        return $this->active($record);
    }

    public function heldBy(Model $record, Authenticatable $user): bool
    {
        $lock = $this->active($record);

        return $lock !== null && $lock->user_id === LockUsers::key($user);
    }

    public function lockedByOther(Model $record, Authenticatable $user): bool
    {
        $lock = $this->active($record);

        return $lock !== null && $lock->user_id !== LockUsers::key($user);
    }

    /**
     * Take the lock if it is free, expired, or already the user's (then it is only extended). Returns the lock
     * when the user holds it afterwards, null when somebody else does.
     *
     * `$holder` identifies the page instance; pass it when a page is opened, and null from a heartbeat so a
     * renewal never steals the instance from a newer tab of the same user.
     */
    public function acquire(Model $record, Authenticatable $user, ?string $holder = null): ?RecordLock
    {
        $userKey = LockUsers::key($user);
        $now = now();
        $expires = $now->copy()->addSeconds(RecordLockPlugin::resolve()->getLockTimeout());
        $model = $this->lockModel();

        $query = fn () => $model::query()
            ->where('lockable_type', $record->getMorphClass())
            ->where('lockable_id', (string) $record->getKey());

        $lock = $query()->first();

        if ($lock === null) {
            try {
                return $model::query()->create([
                    'lockable_type' => $record->getMorphClass(),
                    'lockable_id' => (string) $record->getKey(),
                    'user_id' => $userKey,
                    'holder' => $holder,
                    'locked_at' => $now,
                    'expires_at' => $expires,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Somebody inserted between our SELECT and INSERT: decide with the conditional UPDATE below.
                $lock = $query()->first();
            }

            if ($lock === null) {
                return null;
            }
        }

        $takingOver = $lock->user_id !== $userKey;
        $values = ['expires_at' => $expires, 'updated_at' => $now];

        if ($takingOver) {
            $values += ['user_id' => $userKey, 'holder' => $holder, 'locked_at' => $now];
        } elseif ($holder !== null) {
            $values['holder'] = $holder;
        }

        $affected = $query()
            ->where(function ($q) use ($userKey, $now): void {
                $q->where('user_id', $userKey)
                    ->orWhereNull('expires_at')
                    ->orWhere('expires_at', '<=', $now);
            })
            ->update($values);

        $fresh = $query()->first();

        return $affected > 0 && $fresh !== null && $fresh->user_id === $userKey ? $fresh : null;
    }

    /**
     * Release the lock if the user holds it. With `$holder` only the page instance that took it releases.
     */
    public function release(Model $record, Authenticatable $user, ?string $holder = null): bool
    {
        $model = $this->lockModel();

        return $model::query()
            ->where('lockable_type', $record->getMorphClass())
            ->where('lockable_id', (string) $record->getKey())
            ->where('user_id', LockUsers::key($user))
            ->when($holder !== null, fn ($q) => $q->where('holder', $holder))
            ->delete() > 0;
    }

    /**
     * Drop whatever lock the record has, whoever holds it (an administrative release).
     */
    public function forceRelease(Model $record): bool
    {
        $model = $this->lockModel();

        return $model::query()
            ->where('lockable_type', $record->getMorphClass())
            ->where('lockable_id', (string) $record->getKey())
            ->delete() > 0;
    }

    /**
     * Replace the holder with `$user`, whoever it was. Authorization is the caller's job
     * ({@see RecordLockPlugin::canUnlock()}).
     */
    public function takeOver(Model $record, Authenticatable $user, ?string $holder = null): RecordLock
    {
        $previous = $this->active($record);
        $previousUser = $previous?->user;

        $this->forceRelease($record);
        $lock = $this->acquire($record, $user, $holder);

        if ($lock === null) {
            // Lost a race against a third user between the release and the insert: report it as a plain failure.
            throw new \RuntimeException('Could not take the record lock over.');
        }

        if ($previous !== null && $previous->user_id !== LockUsers::key($user)) {
            event(new RecordLockTakenOver($record, $previousUser, $user));
        }

        return $lock;
    }

    public function pruneExpired(): int
    {
        $model = $this->lockModel();

        return $model::query()->expired()->delete();
    }
}
