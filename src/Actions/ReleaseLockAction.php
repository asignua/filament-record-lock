<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Actions;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * A table record action that releases the lock of its row. The row is either a model that uses `HasLocks`
 * (a resource table) or a {@see RecordLock} (the manager). Visible only to those who may unlock.
 */
class ReleaseLockAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'releaseLock';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-lock::filament-record-lock.actions.release'))
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('filament-record-lock::filament-record-lock.actions.release_heading'))
            ->modalDescription(__('filament-record-lock::filament-record-lock.actions.release_description'))
            ->visible(fn (?Model $record): bool => $record !== null && static::canRelease($record))
            ->action(function (?Model $record): void {
                if ($record === null || !static::release($record)) {
                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-record-lock::filament-record-lock.actions.released'))
                    ->send();
            });
    }

    /**
     * Is there an active lock on this row that the current user may release?
     */
    public static function canRelease(Model $row): bool
    {
        $plugin = RecordLockPlugin::resolve();

        if ($row instanceof RecordLock) {
            return $row->isActive() && $plugin->canUnlock($row->lockableOrNull());
        }

        return app(RecordLocker::class)->activeForDisplay($row) !== null && $plugin->canUnlock($row);
    }

    /**
     * Release the lock of this row (a locked model or a lock row). False when not allowed or nothing to release.
     */
    public static function release(Model $row): bool
    {
        if (!static::canRelease($row)) {
            return false;
        }

        if ($row instanceof RecordLock) {
            return (bool) $row->delete();
        }

        return app(RecordLocker::class)->forceRelease($row);
    }
}
