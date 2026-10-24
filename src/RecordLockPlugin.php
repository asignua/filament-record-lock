<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\Resources\RecordLockResource;
use Asignua\FilamentRecordLock\Support\LockUsers;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Pessimistic record locking for Filament panels.
 *
 *     ->plugin(RecordLockPlugin::make()
 *         ->lockTimeout(120)
 *         ->presencePollingInterval(15)
 *         ->unlockerGate('unlockRecordLock')
 *         ->gate('viewRecordLocks'))
 *
 * Every option falls back to config/filament-record-lock.php. The method names follow kenepa/resource-lock.
 */
class RecordLockPlugin implements Plugin
{
    public const string ID = 'asignua-filament-record-lock';

    /** Render-hook scope that the lock traits add to their page; the observer view is registered for it. */
    public const string HOOK_SCOPE = 'asignua-filament-record-lock';

    protected ?bool $displayResourceLockOwner = null;

    protected ?bool $navigationBadge = null;

    protected ?string $navigationIcon = null;

    protected ?string $navigationLabel = null;

    protected ?string $pluralLabel = null;

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?bool $limitedAccessToResourceLockManager = null;

    protected ?string $gate = null;

    protected ?bool $shouldRegisterNavigation = null;

    protected ?bool $managerEnabled = null;

    protected ?bool $unlockerLimitedAccess = null;

    protected ?string $unlockerGate = null;

    protected bool|Closure|null $authorizeUnlock = null;

    protected bool|Closure|null $authorizeManager = null;

    /** @var class-string|null */
    protected ?string $resourceClass = null;

    /** @var class-string<Model>|null */
    protected ?string $userModel = null;

    /** @var class-string<RecordLock>|null */
    protected ?string $resourceLockModel = null;

    protected ?int $lockTimeout = null;

    protected ?bool $checkLocksBeforeSaving = null;

    /** @var class-string|null */
    protected ?string $resourceLockOwnerAction = null;

    protected ?Closure $ownerUsing = null;

    protected ?bool $usesPollingToDetectPresence = null;

    protected ?int $presencePollingInterval = null;

    protected ?bool $pollingKeepAlive = null;

    protected ?bool $pollingVisible = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(static::ID);
    }

    /**
     * The plugin of the current panel when it has one, otherwise a bare instance that reads the config only
     * (the console, a queue job, a test that never touched a panel).
     */
    public static function resolve(): static
    {
        $panel = Filament::getCurrentPanel();

        if ($panel !== null && $panel->hasPlugin(static::ID)) {
            /** @var static */
            return $panel->getPlugin(static::ID);
        }

        return static::make();
    }

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        if ($this->isManagerEnabled()) {
            $panel->resources([$this->getResourceClass()]);
        }

        $panel->authenticatedRoutes(function (): void {
            Route::post('/record-lock/release', Http\Controllers\ReleaseLockController::class)->name('record-lock.release');
        });
    }

    public function boot(Panel $panel): void
    {
        /** @var view-string $observerView */
        $observerView = 'filament-record-lock::lock-observer';

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_START,
            fn (): string => view($observerView)->render(),
            scopes: [static::HOOK_SCOPE],
        );
    }

    // ---- who may do what ---------------------------------------------------------------------------------

    /**
     * Who may take a locked record over, release locks in a table and in the manager. A closure, a bool, or
     * (default) the unlocker gate ability - and when that is not defined, nobody. Kenepa-style
     * `unlockerLimitedAccess(false)` opens it to every user.
     */
    public function authorizeUnlock(bool|Closure|null $callback): static
    {
        $this->authorizeUnlock = $callback;

        return $this;
    }

    /**
     * Who may open the "Record locks" manager.
     */
    public function authorizeManager(bool|Closure|null $callback): static
    {
        $this->authorizeManager = $callback;

        return $this;
    }

    public function canUnlock(?Model $record = null, ?Authenticatable $user = null): bool
    {
        $user ??= LockUsers::current();

        if ($user === null) {
            return false;
        }

        if ($this->authorizeUnlock !== null) {
            return $this->authorizeUnlock instanceof Closure
                ? (bool) app()->call($this->authorizeUnlock, ['record' => $record, 'user' => $user])
                : $this->authorizeUnlock;
        }

        if (!$this->shouldLimitUnlockerAccess()) {
            return true;
        }

        $ability = $this->getUnlockerGate();

        return Gate::has($ability) && Gate::forUser($user)->allows($ability, $record);
    }

    public function canAccessManager(): bool
    {
        $user = LockUsers::current();

        if ($user === null) {
            return false;
        }

        if ($this->authorizeManager !== null) {
            return $this->authorizeManager instanceof Closure
                ? (bool) app()->call($this->authorizeManager, ['user' => $user])
                : $this->authorizeManager;
        }

        if (!$this->shouldLimitAccessToResourceLockManager()) {
            return true;
        }

        $ability = $this->getGate();

        return Gate::has($ability) && Gate::forUser($user)->allows($ability);
    }

    // ---- lock behaviour ----------------------------------------------------------------------------------

    public function lockTimeout(?int $seconds): static
    {
        $this->lockTimeout = $seconds;

        return $this;
    }

    public function getLockTimeout(): int
    {
        return max(1, $this->lockTimeout ?? (int) config('filament-record-lock.lock_timeout', 120));
    }

    public function checkLocksBeforeSaving(bool $check = true): static
    {
        $this->checkLocksBeforeSaving = $check;

        return $this;
    }

    public function shouldCheckLocksBeforeSaving(): bool
    {
        return $this->checkLocksBeforeSaving ?? (bool) config('filament-record-lock.check_locks_before_saving', true);
    }

    public function usesPollingToDetectPresence(bool $enable = true): static
    {
        $this->usesPollingToDetectPresence = $enable;

        return $this;
    }

    public function shouldUsePollingToDetectPresence(): bool
    {
        return $this->usesPollingToDetectPresence ?? (bool) config('filament-record-lock.polling.enabled', true);
    }

    public function presencePollingInterval(int $seconds): static
    {
        $this->presencePollingInterval = $seconds;

        return $this;
    }

    public function getPresencePollingInterval(): int
    {
        return max(1, $this->presencePollingInterval ?? (int) config('filament-record-lock.polling.interval', 15));
    }

    public function pollingKeepAlive(bool $keepAlive = true): static
    {
        $this->pollingKeepAlive = $keepAlive;

        return $this;
    }

    public function shouldUsePollingKeepAlive(): bool
    {
        return $this->pollingKeepAlive ?? (bool) config('filament-record-lock.polling.keep_alive', false);
    }

    public function pollingVisible(bool $visible = true): static
    {
        $this->pollingVisible = $visible;

        return $this;
    }

    public function shouldUsePollingVisible(): bool
    {
        return $this->pollingVisible ?? (bool) config('filament-record-lock.polling.visible', false);
    }

    // ---- unlocker / owner display ------------------------------------------------------------------------

    public function unlockerLimitedAccess(bool $limited = true): static
    {
        $this->unlockerLimitedAccess = $limited;

        return $this;
    }

    public function shouldLimitUnlockerAccess(): bool
    {
        return $this->unlockerLimitedAccess ?? (bool) config('filament-record-lock.unlocker.limited_access', true);
    }

    public function unlockerGate(?string $gate): static
    {
        $this->unlockerGate = $gate;

        return $this;
    }

    public function getUnlockerGate(): string
    {
        return $this->unlockerGate ?? (string) config('filament-record-lock.unlocker.ability', 'unlockRecordLock');
    }

    public function displayResourceLockOwner(bool $display = true): static
    {
        $this->displayResourceLockOwner = $display;

        return $this;
    }

    public function shouldDisplayResourceLockOwner(): bool
    {
        return $this->displayResourceLockOwner ?? (bool) config('filament-record-lock.lock_notice.display_owner', true);
    }

    /**
     * A class with `execute(Model $user): string` (kenepa's GetResourceLockOwnerAction contract).
     *
     * @param class-string|null $action
     */
    public function resourceLockOwnerAction(?string $action): static
    {
        $this->resourceLockOwnerAction = $action;

        return $this;
    }

    /**
     * How the holder is named in the notice and the manager: `fn (Model $user): string`.
     */
    public function ownerUsing(?Closure $callback): static
    {
        $this->ownerUsing = $callback;

        return $this;
    }

    public function ownerName(Model|Authenticatable|null $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($this->ownerUsing !== null) {
            return (string) ($this->ownerUsing)($user);
        }

        if ($this->resourceLockOwnerAction !== null) {
            return (string) app($this->resourceLockOwnerAction)->execute($user);
        }

        return LockUsers::name($user);
    }

    // ---- models ------------------------------------------------------------------------------------------

    /**
     * @param class-string<Model>|null $model
     */
    public function userModel(?string $model): static
    {
        $this->userModel = $model;

        return $this;
    }

    /**
     * @return class-string<Model>
     */
    public function getUserModel(): string
    {
        return $this->userModel ?? LockUsers::model();
    }

    /**
     * @param class-string<RecordLock>|null $model
     */
    public function resourceLockModel(?string $model): static
    {
        $this->resourceLockModel = $model;

        return $this;
    }

    /**
     * @return class-string<RecordLock>
     */
    public function getLockModel(): string
    {
        if ($this->resourceLockModel !== null) {
            return $this->resourceLockModel;
        }

        $configured = config('filament-record-lock.models.lock');

        /** @var class-string<RecordLock> */
        return is_string($configured) && is_a($configured, RecordLock::class, true) ? $configured : RecordLock::class;
    }

    // ---- manager -----------------------------------------------------------------------------------------

    public function manager(bool $enabled = true): static
    {
        $this->managerEnabled = $enabled;

        return $this;
    }

    public function isManagerEnabled(): bool
    {
        return $this->managerEnabled ?? (bool) config('filament-record-lock.manager.enabled', true);
    }

    /**
     * @param class-string|null $class
     */
    public function resourceClass(?string $class): static
    {
        $this->resourceClass = $class;

        return $this;
    }

    /**
     * @return class-string
     */
    public function getResourceClass(): string
    {
        return $this->resourceClass ?? RecordLockResource::class;
    }

    public function limitedAccessToResourceLockManager(bool $limited = true): static
    {
        $this->limitedAccessToResourceLockManager = $limited;

        return $this;
    }

    public function shouldLimitAccessToResourceLockManager(): bool
    {
        return $this->limitedAccessToResourceLockManager ?? (bool) config('filament-record-lock.manager.limited_access', true);
    }

    public function gate(?string $gate): static
    {
        $this->gate = $gate;

        return $this;
    }

    public function getGate(): string
    {
        return $this->gate ?? (string) config('filament-record-lock.manager.ability', 'viewRecordLocks');
    }

    public function registerNavigation(bool $register = true): static
    {
        $this->shouldRegisterNavigation = $register;

        return $this;
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->shouldRegisterNavigation ?? (bool) config('filament-record-lock.manager.should_register_navigation', true);
    }

    public function navigationBadge(bool $show = true): static
    {
        $this->navigationBadge = $show;

        return $this;
    }

    public function shouldShowNavigationBadge(): bool
    {
        return $this->navigationBadge ?? (bool) config('filament-record-lock.manager.navigation_badge', false);
    }

    public function navigationIcon(?string $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string
    {
        return $this->navigationIcon ?? (string) config('filament-record-lock.manager.navigation_icon', 'heroicon-o-lock-closed');
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function pluralLabel(?string $label): static
    {
        $this->pluralLabel = $label;

        return $this;
    }

    public function getPluralLabel(): ?string
    {
        return $this->pluralLabel;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        $group = $this->navigationGroup ?? config('filament-record-lock.manager.navigation_group');

        return is_string($group) ? $group : null;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->navigationSort ?? config('filament-record-lock.manager.navigation_sort');

        return is_numeric($sort) ? (int) $sort : null;
    }
}
