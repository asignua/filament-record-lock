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
     * `$holder` identifies the page instance. A page passes it when it OPENS (`$steal` true: the newest tab owns
     * the release token) and from a heartbeat with `$steal` false: then it is stored only when the row is created
     * or has no token yet, so a renewal never takes the token from a newer tab but a re-created row is still
     * releasable by the page that made it.
     */
    public function acquire(Model $record, Authenticatable $user, ?string $holder = null, bool $steal = true): ?RecordLock
    {
        $userKey = LockUsers::key($user);
        $now = now();
        $expires = $now->copy()->addSeconds(RecordLockPlugin::resolve()->getLockTimeout());

        $lock = $this->find($record);

        if ($lock === null) {
            try {
                return $this->lockModel()::query()->create([
                    'lockable_type' => $record->getMorphClass(),
                    'lockable_id' => (string) $record->getKey(),
                    'user_id' => $userKey,
                    'holder' => $holder,
                    'locked_at' => $now,
                    'expires_at' => $expires,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Somebody inserted between our SELECT and INSERT: decide with the conditional UPDATE below.
                $lock = $this->find($record);
            }

            if ($lock === null) {
                return null;
            }
        }

        $values = ['expires_at' => $expires, 'updated_at' => $now];

        if ($lock->user_id !== $userKey) {
            $values += ['user_id' => $userKey, 'holder' => $holder, 'locked_at' => $now];
        } elseif ($holder !== null && ($steal || $lock->holder === null)) {
            $values['holder'] = $holder;
        }

        // The affected-rows count is NOT trusted: MySQL reports CHANGED rows, so a renewal in the same second
        // (identical values) says 0 for the holder. The row is read back and judged by what it says.
        $this->conditionalUpdate(
            $this->lockModel()::query()
                ->whereKey($lock->getKey())
                ->where(function ($q) use ($userKey, $now): void {
                    $q->where('user_id', $userKey)
                        ->orWhereNull('expires_at')
                        ->orWhere('expires_at', '<=', $now);
                }),
            $values,
        );

        $fresh = $this->find($record);

        return $fresh !== null && $fresh->user_id === $userKey && $fresh->isActive() ? $fresh : null;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<RecordLock> $query
     * @param array<string, mixed>                              $values
     */
    protected function conditionalUpdate($query, array $values): int
    {
        return $query->update($values);
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
     * Replace the holder with `$user`, whoever it was, in ONE statement (no window between "release" and
     * "acquire" in which the old holder's heartbeat could re-insert). Authorization is the caller's job
     * ({@see RecordLockPlugin::canUnlock()}).
     */
    public function takeOver(Model $record, Authenticatable $user, ?string $holder = null): RecordLock
    {
        $userKey = LockUsers::key($user);
        $previous = $this->active($record);
        $previousUser = $previous?->user;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $now = now();
            $existing = $this->find($record);

            if ($existing !== null) {
                $this->conditionalUpdate(
                    $this->lockModel()::query()->whereKey($existing->getKey()),
                    [
                        'user_id' => $userKey,
                        'holder' => $holder,
                        'locked_at' => $now,
                        'expires_at' => $now->copy()->addSeconds(RecordLockPlugin::resolve()->getLockTimeout()),
                        'updated_at' => $now,
                    ],
                );
            }

            // Missing row (nobody held it, or it vanished meanwhile): the insert path of acquire().
            $lock = $this->find($record);
            $lock = $lock !== null && $lock->user_id === $userKey && $lock->isActive()
                ? $lock
                : ($lock === null ? $this->acquire($record, $user, $holder) : null);

            if ($lock !== null) {
                if ($previous !== null && $previous->user_id !== $userKey) {
                    event(new RecordLockTakenOver($record, $previousUser, $user));
                }

                return $lock;
            }
        }

        throw new \RuntimeException('Could not take the record lock over.');
    }

    public function pruneExpired(): int
    {
        $model = $this->lockModel();

        return $model::query()->expired()->delete();
    }
}
