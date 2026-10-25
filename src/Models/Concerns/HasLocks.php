<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Models\Concerns;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\LockUsers;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Support\StringKeyMorphOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Put it on every model whose Edit page uses {@see \Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesResourceLock}.
 * The method names (lock, unlock, isLocked, isLockedByCurrentUser...) are those of kenepa/resource-lock.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
// @phpstan-ignore-next-line trait.unused (used by the host's models; the tests exercise it)
trait HasLocks
{
    /**
     * @return MorphOne<RecordLock, $this>
     */
    public function recordLock(): MorphOne
    {
        $instance = $this->newRelatedInstance(RecordLockPlugin::resolve()->getLockModel());
        [$type, $id] = $this->getMorphs('lockable', null, null);
        $table = $instance->getTable();

        /** @var MorphOne<RecordLock, $this> */
        return new StringKeyMorphOne($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $this->getKeyName());
    }

    /**
     * Same relation under the name kenepa/resource-lock used.
     *
     * @return MorphOne<RecordLock, $this>
     */
    public function resourceLock(): MorphOne
    {
        return $this->recordLock();
    }

    /**
     * Eager-load the lock and its holder; use it in the query of a table that shows a RecordLockColumn.
     *
     * @param Builder<static> $query
     */
    public function scopeWithRecordLock(Builder $query): void
    {
        $query->with('recordLock.user');
    }

    /**
     * Lock the record for the current user (or extend the lock they hold). False when somebody else holds it.
     */
    public function lock(): bool
    {
        $user = LockUsers::current();

        if ($user === null) {
            return false;
        }

        $locked = app(RecordLocker::class)->acquire($this, $user) !== null;
        $this->unsetRelation('recordLock');

        return $locked;
    }

    /**
     * Release the lock when it is the current user's or is expired; `force: true` drops any lock.
     */
    public function unlock(bool $force = false): bool
    {
        $locker = app(RecordLocker::class);
        $user = LockUsers::current();
        $this->unsetRelation('recordLock');

        if ($force) {
            $locker->forceRelease($this);

            return true;
        }

        if ($user !== null && $locker->release($this, $user)) {
            return true;
        }

        $lock = $locker->find($this);

        if ($lock !== null && $lock->isExpired()) {
            $locker->forceRelease($this);
        }

        return $lock === null || $lock->isExpired();
    }

    public function isLocked(): bool
    {
        return app(RecordLocker::class)->active($this) !== null;
    }

    public function isUnlocked(): bool
    {
        return !$this->isLocked();
    }

    public function isLockedByCurrentUser(): bool
    {
        $user = LockUsers::current();

        return $user !== null && app(RecordLocker::class)->heldBy($this, $user);
    }

    public function lockCreatedByCurrentUser(): bool
    {
        return $this->isLockedByCurrentUser();
    }

    public function hasExpiredLock(): bool
    {
        $lock = app(RecordLocker::class)->find($this);

        return $lock === null || $lock->isExpired();
    }
}
