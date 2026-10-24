<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tables\Columns;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\LockUsers;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * A lock icon on rows that somebody is editing; the tooltip names the holder.
 *
 *     ->columns([RecordLockColumn::make('lock')])
 *     ->modifyQueryUsing(fn ($query) => $query->withRecordLock())   // one query for all locks of the page
 *
 * State: `other` (held by someone else), `mine` (held by you), null (free).
 */
class RecordLockColumn extends IconColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('')
            ->alignCenter()
            ->getStateUsing(function (Model $record): ?string {
                $lock = $this->activeLockOf($record);
                $user = LockUsers::current();

                if ($lock === null || $user === null) {
                    return null;
                }

                return $lock->user_id === LockUsers::key($user) ? 'mine' : 'other';
            })
            ->icon(fn (?string $state): ?string => match ($state) {
                'other' => 'heroicon-s-lock-closed',
                'mine' => 'heroicon-o-pencil-square',
                default => null,
            })
            ->color(fn (?string $state): string => $state === 'other' ? 'danger' : 'success')
            ->tooltip(function (Model $record): ?string {
                $lock = $this->activeLockOf($record);

                if ($lock === null) {
                    return null;
                }

                $user = LockUsers::current();

                if ($user !== null && $lock->user_id === LockUsers::key($user)) {
                    return __('filament-record-lock::filament-record-lock.column.mine');
                }

                $plugin = RecordLockPlugin::resolve();
                $name = $plugin->shouldDisplayResourceLockOwner() ? $plugin->ownerName($lock->user) : null;

                return $name !== null
                    ? __('filament-record-lock::filament-record-lock.column.other', ['user' => $name, 'since' => $lock->locked_at?->diffForHumans()])
                    : __('filament-record-lock::filament-record-lock.column.other_unknown');
            });
    }

    protected function activeLockOf(Model $record): ?RecordLock
    {
        return app(RecordLocker::class)->activeForDisplay($record);
    }
}
