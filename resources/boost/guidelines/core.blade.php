## Filament Record Lock (asignua/filament-record-lock)

One editor per record: a lock is taken when an Edit page opens, renewed by a heartbeat, released on save/leave, and a second user gets a blocking notice. Successor of `kenepa/resource-lock` (same trait and plugin method names).

- Register the plugin on the panel: `->plugin(Asignua\FilamentRecordLock\RecordLockPlugin::make())`, then `php artisan vendor:publish --tag=filament-record-lock-migrations && php artisan migrate`. The migration is a publishable stub, it is not auto-loaded.
- Model: `use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;`. Edit page: `use Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesResourceLock;` (an `EditRecord`). Modal edits on a `ManageRecords` page: `UsesSimpleResourceLock`.
- If a page overrides `mount()` or `save()`, it must call `parent::` - the traits hook those two.
- Nobody may take a lock over or open the "Record locks" manager until a gate exists: `Gate::define('unlockRecordLock', fn (User $user, ?Model $record = null) => ...)` and `Gate::define('viewRecordLocks', fn (User $user) => ...)`, or `->authorizeUnlock()` / `->authorizeManager()` on the plugin. An undefined ability means "no".
- Table: `RecordLockColumn::make('lock')` plus `->modifyQueryUsing(fn ($q) => $q->withRecordLock())` (otherwise one query per row); `ReleaseLockAction` / `ReleaseLocksBulkAction` for releasing.
- Options are fluent on the plugin (`lockTimeout()` seconds, `presencePollingInterval()` seconds, `checkLocksBeforeSaving()`, `ownerUsing()`) and fall back to `config/filament-record-lock.php`. Keep the timeout at least twice the polling interval.
- Server-side access goes through `Asignua\FilamentRecordLock\Support\RecordLocker` (`acquire`, `active`, `release`, `forceRelease`, `takeOver`); `RecordLockTakenOver` is dispatched when a lock changes hands.
- Keys are stored as strings, so integer, UUID and ULID primary keys all work. Expired locks never block; `record-lock:prune` only tidies the table.
