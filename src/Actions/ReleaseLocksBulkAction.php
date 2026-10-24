<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Actions;

use Asignua\FilamentRecordLock\RecordLockPlugin;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bulk variant of {@see ReleaseLockAction}.
 */
class ReleaseLocksBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'releaseLocks';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-lock::filament-record-lock.actions.release_selected'))
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('filament-record-lock::filament-record-lock.actions.release_selected_heading'))
            ->modalDescription(__('filament-record-lock::filament-record-lock.actions.release_description'))
            ->deselectRecordsAfterCompletion()
            ->visible(fn (): bool => RecordLockPlugin::resolve()->canUnlock())
            ->action(function (Collection $records): void {
                $released = 0;

                foreach ($records as $row) {
                    if (ReleaseLockAction::release($row)) {
                        $released++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title(__('filament-record-lock::filament-record-lock.actions.released_count', ['count' => $released]))
                    ->send();
            });
    }
}
