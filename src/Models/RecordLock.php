<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Models;

use Asignua\FilamentRecordLock\Support\LockUsers;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $lockable_type
 * @property string $lockable_id
 * @property string $user_id
 * @property string|null $holder
 * @property CarbonInterface|null $locked_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model|null $lockable
 * @property-read Model|null $user
 */
class RecordLock extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('filament-record-lock.table', 'record_locks');
    }

    public function getConnectionName(): ?string
    {
        $connection = config('filament-record-lock.connection');

        return is_string($connection) && $connection !== '' ? $connection : parent::getConnectionName();
    }

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(LockUsers::model(), 'user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * @param Builder<static> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('expires_at'), '>', now());
    }

    /**
     * @param Builder<static> $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull($this->qualifyColumn('expires_at'))
                ->orWhere($this->qualifyColumn('expires_at'), '<=', now());
        });
    }
}
