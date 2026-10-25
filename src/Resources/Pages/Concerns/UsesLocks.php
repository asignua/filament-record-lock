<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Resources\Pages\Concerns;

use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\LockUsers;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * The lock logic that the edit-page trait and the modal-action trait share. It keeps its whole state in public
 * Livewire properties (they are what the observer view reads), and every method that changes ownership asks the
 * server again - nothing here trusts what the browser sends back.
 */
// @phpstan-ignore-next-line trait.unused (used by the host's pages; the tests exercise it)
trait UsesLocks
{
    /** The blocking notice is shown. */
    #[Locked]
    public bool $recordLockBlocked = false;

    /** Why: `locked` (somebody holds it), `taken_over` (it was taken from us), `stale` (changed while our lock had lapsed). */
    #[Locked]
    public ?string $recordLockReason = null;

    #[Locked]
    public ?string $recordLockOwner = null;

    #[Locked]
    public ?string $recordLockSince = null;

    /** The observer (heartbeat, release on leave) is on for the current record. */
    #[Locked]
    public bool $recordLockActive = false;

    /** What the notice offers; filled when the page takes (or fails to take) the lock. */
    #[Locked]
    public bool $recordLockCanUnlock = false;

    #[Locked]
    public ?string $recordLockViewUrl = null;

    #[Locked]
    public ?string $recordLockBackUrl = null;

    /** The id of OUR lock row and the token of this page instance: both go to the release-on-leave beacon. */
    #[Locked]
    public int|string|null $recordLockId = null;

    #[Locked]
    public ?string $recordLockHolder = null;

    /** The record's `updated_at` as it was when the form was loaded; detects a change made while our lock had lapsed. */
    #[Locked]
    public ?string $recordLockVersion = null;

    /**
     * The record this page locks right now (null = nothing to lock).
     */
    abstract protected function recordLockRecord(): ?Model;

    /**
     * Reload the record and refill the form after the page gained or regained the lock.
     */
    abstract protected function recordLockRefresh(): void;

    /**
     * Adds the scope that the observer view (heartbeat, release on leave, the notice) is registered for.
     *
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [...parent::getRenderHookScopes(), RecordLockPlugin::HOOK_SCOPE];
    }

    protected function recordLockLocker(): RecordLocker
    {
        return app(RecordLocker::class);
    }

    /**
     * Where "Back to list" goes; null = a button that only closes the modal.
     */
    public function recordLockReturnUrl(): ?string
    {
        return method_exists($this, 'getResource') ? $this->getResource()::getUrl('index') : null;
    }

    /**
     * The read-only page of the record, when the resource has one the user may open.
     */
    protected function resolveRecordLockViewUrl(): ?string
    {
        $record = $this->recordLockRecord();

        if ($record === null || !method_exists($this, 'getResource')) {
            return null;
        }

        $resource = $this->getResource();

        if (!$resource::hasPage('view') || !$resource::canView($record)) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $record]);
    }

    public function recordLockReleaseUrl(): string
    {
        return route('filament.'.Filament::getCurrentPanel()?->getId().'.record-lock.release');
    }

    /**
     * Take the lock when the page opens; show the notice when somebody else holds it.
     */
    protected function initializeRecordLock(?Model $record = null): void
    {
        $record ??= $this->recordLockRecord();
        $user = LockUsers::current();

        if ($record === null || $user === null) {
            return;
        }

        $this->recordLockActive = true;
        $this->recordLockHolder = Str::random(32);
        $this->recordLockBackUrl = $this->recordLockReturnUrl();
        $this->recordLockViewUrl = $this->resolveRecordLockViewUrl();
        $this->recordLockCanUnlock = RecordLockPlugin::resolve()->canUnlock($record, $user);
        $this->recordLockVersion = $this->recordLockVersionOf($record);

        $lock = $this->recordLockLocker()->acquire($record, $user, $this->recordLockHolder);

        if ($lock === null) {
            $this->blockRecordLock('locked', $record);

            return;
        }

        $this->recordLockId = $lock->getKey();
        $this->recordLockBlocked = false;
        $this->recordLockReason = null;
    }

    /**
     * Stop observing (the record changed or the modal closed) and let the lock go.
     */
    protected function finishRecordLock(): void
    {
        $this->releaseRecordLock();
        $this->recordLockActive = false;
        $this->recordLockBlocked = false;
        $this->recordLockReason = null;
        $this->recordLockId = null;
    }

    protected function releaseRecordLock(): void
    {
        $record = $this->recordLockRecord();
        $user = LockUsers::current();

        if ($record !== null && $user !== null && $this->recordLockActive) {
            $this->recordLockLocker()->release($record, $user, $this->recordLockHolder);
        }
    }

    /**
     * The heartbeat (`wire:poll`). Renews our lock, or notices that we lost it, or - while the notice is up -
     * learns that the holder left.
     */
    public function recordLockHeartbeat(): void
    {
        $record = $this->recordLockRecord();

        if (!$this->recordLockActive || $record === null) {
            return;
        }

        if ($this->recordLockBlocked) {
            $this->refreshRecordLockOwner($record);

            if ($this->recordLockReason === 'locked' && $this->recordLockLocker()->active($record) === null) {
                $this->recordLockReload();
            }

            return;
        }

        $this->recordLockVerify();
    }

    /**
     * Is the lock still ours? Extends it when it is (or when it lapsed and nobody took it), otherwise puts the
     * page into the blocked state. Also the gate in front of every save.
     */
    protected function recordLockVerify(): bool
    {
        $record = $this->recordLockRecord();
        $user = LockUsers::current();

        if ($record === null || $user === null || !$this->recordLockActive) {
            return true;
        }

        if ($this->recordLockBlocked) {
            return false;
        }

        $locker = $this->recordLockLocker();
        $wasHeld = $locker->heldBy($record, $user);
        $lock = $locker->acquire($record, $user, $this->recordLockHolder, steal: false);

        if ($lock === null) {
            $this->blockRecordLock('taken_over', $record);

            Notification::make()
                ->warning()
                ->title(__('filament-record-lock::filament-record-lock.notifications.taken_over_title'))
                ->body(__('filament-record-lock::filament-record-lock.notifications.taken_over_body'))
                ->persistent()
                ->send();

            return false;
        }

        $this->recordLockId = $lock->getKey();

        // The lock had lapsed and was free again: somebody may have saved the record in the gap.
        if (!$wasHeld && $this->recordLockVersion !== $this->recordLockVersionOf($record, fresh: true)) {
            $this->blockRecordLock('stale', $record);

            return false;
        }

        return true;
    }

    /**
     * May the save go ahead? False after the page was told why (the notice and a toast).
     */
    protected function recordLockPermitsSave(): bool
    {
        if (!RecordLockPlugin::resolve()->shouldCheckLocksBeforeSaving()) {
            return true;
        }

        if ($this->recordLockVerify()) {
            return true;
        }

        Notification::make()
            ->danger()
            ->title(__('filament-record-lock::filament-record-lock.notifications.save_refused_title'))
            ->body(__('filament-record-lock::filament-record-lock.notifications.save_refused_body'))
            ->send();

        return false;
    }

    /**
     * "Unlock and edit": take the record over from its holder. Server-side gate - the button is only a courtesy.
     */
    public function recordLockTakeOver(): void
    {
        $record = $this->recordLockRecord();
        $user = LockUsers::current();

        if ($record === null || $user === null || !$this->recordLockActive) {
            return;
        }

        if (!RecordLockPlugin::resolve()->canUnlock($record, $user)) {
            Notification::make()
                ->danger()
                ->title(__('filament-record-lock::filament-record-lock.notifications.unlock_denied'))
                ->send();

            return;
        }

        $lock = $this->recordLockLocker()->takeOver($record, $user, $this->recordLockHolder);

        $this->recordLockId = $lock->getKey();
        $this->recordLockBlocked = false;
        $this->recordLockReason = null;

        $this->recordLockRefresh();
        $this->recordLockVersion = $this->recordLockVersionOf($record, fresh: true);
    }

    /**
     * "Reload": the holder is gone (or it was taken from us): fetch the record again and try to hold it.
     */
    public function recordLockReload(): void
    {
        $record = $this->recordLockRecord();
        $user = LockUsers::current();

        if ($record === null || $user === null || !$this->recordLockActive) {
            return;
        }

        $lock = $this->recordLockLocker()->acquire($record, $user, $this->recordLockHolder);

        if ($lock === null) {
            $this->blockRecordLock('locked', $record);

            return;
        }

        $this->recordLockId = $lock->getKey();
        $this->recordLockBlocked = false;
        $this->recordLockReason = null;

        $this->recordLockRefresh();
        $this->recordLockVersion = $this->recordLockVersionOf($record, fresh: true);
    }

    protected function blockRecordLock(string $reason, Model $record): void
    {
        $this->recordLockBlocked = true;
        $this->recordLockReason = $reason;
        $this->recordLockId = null;

        $this->refreshRecordLockOwner($record);
    }

    protected function refreshRecordLockOwner(Model $record): void
    {
        $plugin = RecordLockPlugin::resolve();
        $lock = $this->recordLockLocker()->active($record);

        if ($lock === null || !$plugin->shouldDisplayResourceLockOwner()) {
            $this->recordLockOwner = null;
            $this->recordLockSince = null;

            return;
        }

        $this->recordLockOwner = $plugin->ownerName($lock->user);
        $this->recordLockSince = $lock->locked_at?->isoFormat('LLL');
    }

    protected function recordLockVersionOf(Model $record, bool $fresh = false): ?string
    {
        if (!$record->usesTimestamps() || $record->getUpdatedAtColumn() === null) {
            return null;
        }

        $value = $fresh
            ? $record->newQuery()->withoutGlobalScopes()->whereKey($record->getKey())->value($record->getUpdatedAtColumn())
            : $record->getAttribute($record->getUpdatedAtColumn());

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
