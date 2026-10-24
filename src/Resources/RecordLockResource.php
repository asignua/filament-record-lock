<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Resources;

use Asignua\FilamentRecordLock\Actions\ReleaseLockAction;
use Asignua\FilamentRecordLock\Actions\ReleaseLocksBulkAction;
use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Resources\RecordLockResource\Pages\ListRecordLocks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * The "Record locks" manager: read-only list of locks, with the release actions behind the unlock permission.
 * Create / edit / delete are closed on the resource itself, not only hidden in the UI.
 */
class RecordLockResource extends Resource
{
    public static function getModel(): string
    {
        return RecordLockPlugin::resolve()->getLockModel();
    }

    public static function isPluginActive(): bool
    {
        return Filament::getCurrentPanel()?->hasPlugin(RecordLockPlugin::ID) === true;
    }

    public static function canViewAny(): bool
    {
        return static::isPluginActive() && RecordLockPlugin::resolve()->canAccessManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isPluginActive() && RecordLockPlugin::resolve()->shouldRegisterNavigation();
    }

    public static function getNavigationLabel(): string
    {
        return RecordLockPlugin::resolve()->getNavigationLabel() ?? __('filament-record-lock::filament-record-lock.resource.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('filament-record-lock::filament-record-lock.resource.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return RecordLockPlugin::resolve()->getPluralLabel() ?? __('filament-record-lock::filament-record-lock.resource.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return RecordLockPlugin::resolve()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return RecordLockPlugin::resolve()->getNavigationSort();
    }

    public static function getNavigationIcon(): string
    {
        return RecordLockPlugin::resolve()->getNavigationIcon();
    }

    public static function getNavigationBadge(): ?string
    {
        if (!RecordLockPlugin::resolve()->shouldShowNavigationBadge()) {
            return null;
        }

        /** @var class-string<RecordLock> $model */
        $model = static::getModel();

        return (string) $model::query()->active()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'lockable']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('locked_at', 'desc')
            ->columns([
                TextColumn::make('lockable_type')
                    ->label(__('filament-record-lock::filament-record-lock.table.type'))
                    ->formatStateUsing(fn (string $state): string => static::typeLabel($state))
                    ->badge()
                    ->sortable(),
                TextColumn::make('lockable_id')
                    ->label(__('filament-record-lock::filament-record-lock.table.record'))
                    ->formatStateUsing(fn (RecordLock $record): string => static::recordLabel($record))
                    ->url(fn (RecordLock $record): ?string => static::recordUrl($record))
                    ->searchable(),
                TextColumn::make('user_id')
                    ->label(__('filament-record-lock::filament-record-lock.table.holder'))
                    ->formatStateUsing(fn (RecordLock $record): string => RecordLockPlugin::resolve()->ownerName($record->user) ?? '#'.$record->user_id),
                TextColumn::make('locked_at')
                    ->label(__('filament-record-lock::filament-record-lock.table.since'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('filament-record-lock::filament-record-lock.table.expires'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('filament-record-lock::filament-record-lock.table.status'))
                    ->state(fn (RecordLock $record): string => $record->isActive() ? 'active' : 'expired')
                    ->formatStateUsing(fn (string $state): string => __('filament-record-lock::filament-record-lock.status.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'warning' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('filament-record-lock::filament-record-lock.table.status'))
                    ->trueLabel(__('filament-record-lock::filament-record-lock.status.active'))
                    ->falseLabel(__('filament-record-lock::filament-record-lock.status.expired'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('expires_at', '>', now()),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '<=', now())),
                        blank: fn (Builder $query) => $query,
                    )
                    ->default(true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ReleaseLockAction::make(),
                ]),
            ])
            ->toolbarActions([
                ReleaseLocksBulkAction::make(),
            ])
            ->headerActions([
                static::releaseAllAction(),
            ])
            ->poll('15s');
    }

    protected static function releaseAllAction(): Action
    {
        return Action::make('releaseAll')
            ->label(__('filament-record-lock::filament-record-lock.actions.release_all'))
            ->icon('heroicon-o-lock-open')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('filament-record-lock::filament-record-lock.actions.release_all_description'))
            ->visible(fn (): bool => RecordLockPlugin::resolve()->canUnlock())
            ->action(function (): void {
                if (!RecordLockPlugin::resolve()->canUnlock()) {
                    return;
                }

                static::getModel()::query()->delete();

                Notification::make()
                    ->success()
                    ->title(__('filament-record-lock::filament-record-lock.actions.released_all'))
                    ->send();
            });
    }

    protected static function typeLabel(string $morph): string
    {
        $class = Relation::getMorphedModel($morph) ?? $morph;

        return class_exists($class) && ($resource = Filament::getModelResource($class)) !== null
            ? $resource::getModelLabel()
            : Str::headline(class_basename($class));
    }

    protected static function recordLabel(RecordLock $lock): string
    {
        $lockable = $lock->lockable;

        if ($lockable === null) {
            return '#'.$lock->lockable_id;
        }

        $resource = Filament::getModelResource($lockable);

        return $resource !== null ? (string) $resource::getRecordTitle($lockable) : '#'.$lock->lockable_id;
    }

    protected static function recordUrl(RecordLock $lock): ?string
    {
        $lockable = $lock->lockable;

        if ($lockable === null) {
            return null;
        }

        $resource = Filament::getModelResource($lockable);

        if ($resource === null) {
            return null;
        }

        foreach (['view', 'edit'] as $page) {
            if ($resource::hasPage($page) && $resource::{'can'.ucfirst($page)}($lockable)) {
                return $resource::getUrl($page, ['record' => $lockable]);
            }
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecordLocks::route('/'),
        ];
    }
}
