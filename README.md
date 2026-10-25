# Filament Record Lock

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)

<img class="filament-hidden" src="https://raw.githubusercontent.com/asignua/filament-record-lock/v1.0.0/art/cover.jpg" alt="Filament Record Lock">

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-record-lock/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-record-lock/actions/workflows/tests.yml)

One editor per record. When someone opens a record for editing, Record Lock holds it for them, renews the hold while the
page is open, and gives it back when they save or leave. A second person who opens the same record gets a blocking notice
that says who is editing and since when, with three ways out: back to the list, view it read-only, or - for people you
allow - take it over. The person who lost the record is told on their next heartbeat, and a save from a page that no
longer holds the lock is **refused on the server**, not just hidden in the browser.

It is the successor of `kenepa/resource-lock`, which stopped before Filament 5, with the same model trait, page traits and
plugin methods, so a project moves over by swapping a namespace (see [Migrating from kenepa/resource-lock](#migrating-from-keneparesource-lock)).

- Works with integer, UUID and ULID keys on any Eloquent model.
- A heartbeat renews the lock and notices a takeover; a lock that is not renewed expires on its own.
- Released on leave with `navigator.sendBeacon` - it survives a closed tab - and by a prune command for the rest.
- Closed by default: nobody may take a lock over or open the manager until you define a gate.
- A table column that marks locked rows, record and bulk actions to release locks, and a read-only "Record locks" manager.
- Edit pages **and** edit modals (`ManageRecords`).
- No JavaScript or CSS to build: the notice is Filament's own modal.
- English, Ukrainian, Polish, German, French, Spanish, Italian, Dutch, Brazilian Portuguese and Turkish.

## Screenshots

![The blocking notice](https://raw.githubusercontent.com/asignua/filament-record-lock/v1.0.0/art/locked-notice.jpg)

Dark mode follows the panel:

![The blocking notice in dark mode](https://raw.githubusercontent.com/asignua/filament-record-lock/v1.0.0/art/locked-notice-dark.jpg)
![The lock column and the record actions](https://raw.githubusercontent.com/asignua/filament-record-lock/v1.0.0/art/lock-column.jpg)
![The Record locks manager](https://raw.githubusercontent.com/asignua/filament-record-lock/v1.0.0/art/manager.jpg)

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require asignua/filament-record-lock
php artisan vendor:publish --tag=filament-record-lock-migrations
php artisan migrate
```

Optionally publish the config (`--tag=filament-record-lock-config`).

Register the plugin on your panel:

```php
use Asignua\FilamentRecordLock\RecordLockPlugin;

$panel->plugin(RecordLockPlugin::make());
```

## Usage

**1. The model** gets the trait:

```php
use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;

class Article extends Model
{
    use HasLocks;
}
```

**2. The Edit page** gets one:

```php
use Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesResourceLock;

class EditArticle extends EditRecord
{
    use UsesResourceLock;
}
```

For a resource that edits in a modal on a single page (`ManageRecords`) use `UsesSimpleResourceLock` instead; it locks
the record while the table's `edit` action is open (override `recordLockActionNames()` to lock other actions).

That is the whole setup. Opening the page takes the lock; a second user sees the notice.

**3. Allow some people to take a lock over** (optional). Define the gate; nobody passes until you do:

```php
Gate::define('unlockRecordLock', fn (User $user, ?Model $record = null): bool => $user->isAdmin());
```

**4. Show the locks** (optional):

```php
use Asignua\FilamentRecordLock\Actions\ReleaseLockAction;
use Asignua\FilamentRecordLock\Actions\ReleaseLocksBulkAction;
use Asignua\FilamentRecordLock\Tables\Columns\RecordLockColumn;

$table
    ->modifyQueryUsing(fn (Builder $query) => $query->withRecordLock()) // one query for the whole page
    ->columns([RecordLockColumn::make('lock'), /* ... */])
    ->recordActions([ActionGroup::make([EditAction::make(), ReleaseLockAction::make()])])
    ->toolbarActions([ReleaseLocksBulkAction::make()]);
```

The column shows a lock icon on rows somebody else is editing (a pencil on rows you are editing) and names the holder in
the tooltip. The release actions are visible only to people who pass the unlock permission.

**5. The manager.** The plugin registers a read-only "Record locks" resource. It is closed until the `viewRecordLocks` gate
(or `->authorizeManager()`) lets a user in. It lists active locks (filter for expired ones), releases one, many or all.

### Model API

`HasLocks` keeps the method names of `kenepa/resource-lock`: `lock()`, `unlock(force: false)`, `isLocked()`,
`isUnlocked()`, `isLockedByCurrentUser()`, `lockCreatedByCurrentUser()`, `hasExpiredLock()`, the `resourceLock()` relation
(also `recordLock()`), and a new `withRecordLock()` scope. For code of your own (a job, an API) use the service:

```php
$locker = app(Asignua\FilamentRecordLock\Support\RecordLocker::class);

$locker->acquire($record, $user);        // ?RecordLock - the lock if $user holds it afterwards
$locker->active($record);                // ?RecordLock - whoever holds it
$locker->release($record, $user);
$locker->forceRelease($record);          // administrative
$locker->takeOver($record, $user);       // fires RecordLockTakenOver
```

`Asignua\FilamentRecordLock\Events\RecordLockTakenOver` carries the record, the previous holder and the new one. The previous
holder's open page already shows a notice and a toast at its next heartbeat; listen to the event to also send a mail, a
database notification or a Slack message.

## How it works

- **Acquire.** `mount()` of the page inserts one row per record (a unique index on the morph pair makes the insert the
  tie-breaker when two people open the record in the same millisecond). A row that is expired, or already yours, is
  taken with one conditional `UPDATE`.
- **Heartbeat.** The page polls (`wire:poll`, default every 15 s, only while it holds a record) and extends `expires_at` by
  the lock timeout (default 120 s). A closed laptop stops polling, the lock expires, the record is free.
- **Release on leave.** Every page instance has a random holder token. On `pagehide` and Livewire's
  `livewire:navigating` the page sends `navigator.sendBeacon()` with the lock id, the holder token and the CSRF token to
  `POST /{panel}/record-lock/release`. A beacon is the one request a browser still sends while the page is being torn
  down (a Livewire call would be cancelled), it goes through the panel's session and CSRF middleware, and the server only
  deletes a lock that belongs to **that user and that page instance**. The token also protects against the classic
  race of a reload: the old page's beacon arrives after the new page took the lock, finds another token, and does nothing.
  A save that redirects releases immediately, on the server.
- **Lost lock.** If the lock expired and somebody took the record, the heartbeat shows the "taken over" notice. If it
  expired and nobody took it, the heartbeat quietly takes it again - unless the record was saved by someone else in the
  gap (compared by `updated_at`), in which case the page asks the user to reload instead of letting them overwrite it.
- **Save check.** `save()` and inline field saves verify the lock first (`check_locks_before_saving`, on by default). A
  user who removed the notice with the browser tools still cannot save.
- **A blocked page waits.** While the notice is up the page polls too; when the holder leaves, it reloads the record and
  continues by itself.

One user, several tabs: the lock belongs to the user, so a second tab is not blocked. The latest tab owns the release
token; closing the older tab does not drop the lock, closing the newest does (the other tab's next heartbeat takes it back).

## Configuration

Fluent methods on the plugin override `config/filament-record-lock.php`:

```php
RecordLockPlugin::make()
    ->lockTimeout(120)                    // seconds without a heartbeat before a lock expires
    ->presencePollingInterval(15)         // seconds between heartbeats; keep the timeout >= 2x the interval
    ->usesPollingToDetectPresence()       // false: no heartbeat, the lock lives only `lockTimeout` seconds
    ->pollingKeepAlive()                  // wire:poll.keep-alive - keep polling in background tabs
    ->pollingVisible()                    // wire:poll.visible
    ->checkLocksBeforeSaving()            // refuse a save from a page that lost the lock
    ->displayResourceLockOwner()          // show who holds it in the notice and the tooltip
    ->ownerUsing(fn (User $user) => $user->name.' ('.$user->team->name.')')
    ->unlockerGate('unlockRecordLock')    // the ability that allows taking over / releasing
    ->authorizeUnlock(fn (?Model $record, User $user) => $user->can('update', $record))
    ->gate('viewRecordLocks')             // the ability that opens the manager
    ->authorizeManager(fn (User $user) => $user->isAdmin())
    ->manager(false)                      // do not register the manager at all
    ->navigationGroup('System')->navigationIcon('heroicon-o-lock-closed')->navigationSort(90)
    ->navigationLabel('Locks')->pluralLabel('Locks')->navigationBadge()->registerNavigation(false)
    ->userModel(App\Models\User::class)   // default: the model of the panel's auth guard
    ->resourceLockModel(App\Models\MyLock::class); // must extend RecordLock
```

The config file also has `table` / `connection` (the migration follows them), `lock_notice.display_owner`, and
`schedule` (`record-lock:prune` every 10 minutes; `enabled => false` to schedule it yourself). Expired locks never block
anybody, pruning only keeps the table small.

## Gates

| Ability | Default | Decides |
| --- | --- | --- |
| `unlockRecordLock` (`$user`, `?$record`) | **nobody** | "Unlock and edit" in the notice, `ReleaseLockAction`, the bulk action, "Release all" |
| `viewRecordLocks` (`$user`) | **nobody** | the "Record locks" manager |

An undefined ability means "no", never "yes". `->authorizeUnlock()` / `->authorizeManager()` take a bool or a closure and
win over the gate. The server checks the permission again when "Unlock and edit" is pressed; hiding the button is only a courtesy.

## Gotchas

- **Run the migration.** The table (`record_locks`) is published, not auto-loaded: `vendor:publish --tag=filament-record-lock-migrations`.
- **Timeout vs. polling.** With a 120 s timeout and a 15 s heartbeat there is room for seven missed beats. Setting the
  timeout below the interval makes locks expire between beats while the user is typing.
- **Background tabs.** Livewire slows polling in hidden tabs; the lock may expire and be taken while the user is on another
  tab. That is the intended meaning of "idle". Use `pollingKeepAlive()` if you want to hold a record from a background tab.
- **Eager-load for the column.** `RecordLockColumn` asks the database per row unless the query has `->withRecordLock()`.
- **Actions are guarded too.** On an Edit page every mounted action (Delete, Replicate, your own, actions inside the form)
  is refused like a save when the lock is lost; list read-only ones in `recordLockAllowedActions()`. **Relation managers
  are separate Livewire components and are not covered** - protect their writes with the model policy.
- **Tenant panels.** Locks are not tied to a tenant, so the "Record locks" manager stays shut on a panel with tenancy until you
  narrow it: `->scopeManagerQuery(fn (Builder $query) => $query->whereIn('user_id', ...))` (also applied to "Release all").
- **Lock state is `#[Locked]`** on the page; the browser cannot flip it. Keys are bound as strings when eager loading
  (`withRecordLock()` works on PostgreSQL with integer keys).
- **Your own `mount()` / `save()`** on a page that uses the trait must call `parent::mount()` / `parent::save()`.
- **Custom pages that are not resource pages** can use `UsesLocks` yourself: implement `recordLockRecord()` and
  `recordLockRefresh()`, call `initializeRecordLock()` when the record is opened.
- **Unsaved changes of a user who lost the lock** stay in their browser until they reload; the notice says so.

## Migrating from kenepa/resource-lock

1. `composer remove kenepa/resource-lock && composer require asignua/filament-record-lock`.
2. Swap the namespaces:

   | kenepa/resource-lock | asignua/filament-record-lock |
   | --- | --- |
   | `Kenepa\ResourceLock\ResourceLockPlugin` | `Asignua\FilamentRecordLock\RecordLockPlugin` |
   | `Kenepa\ResourceLock\Models\Concerns\HasLocks` | `Asignua\FilamentRecordLock\Models\Concerns\HasLocks` |
   | `Kenepa\ResourceLock\Resources\Pages\Concerns\UsesResourceLock` | `Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesResourceLock` |
   | `Kenepa\ResourceLock\Resources\Pages\Concerns\UsesSimpleResourceLock` | `Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesSimpleResourceLock` |
   | `Kenepa\ResourceLock\Models\ResourceLock` | `Asignua\FilamentRecordLock\Models\RecordLock` |

3. Drop the old table and run ours; locks are short-lived, nothing needs converting:
   `Schema::dropIfExists('resource_locks')`, then publish and run the new migration.
4. Delete the published `config/resource-lock.php`, publish ours if you want to change anything, and replace the
   `->plugin(ResourceLockPlugin::make()...)` call.
5. Define the gates (see below) - **this is the one change that can surprise you**.

What stays the same: the model methods (`lock`, `unlock`, `isLocked`, `isUnlocked`, `isLockedByCurrentUser`,
`lockCreatedByCurrentUser`, `hasExpiredLock`, `resourceLock()`), the page traits and their names, `lockTimeout()` in
seconds, `checkLocksBeforeSaving()`, `usesPollingToDetectPresence()`, `presencePollingInterval()`, `pollingKeepAlive()`,
`pollingVisible()`, `displayResourceLockOwner()`, `unlockerLimitedAccess()`, `unlockerGate()`,
`limitedAccessToResourceLockManager()`, `gate()`, `userModel()`, `resourceLockModel()`, `resourceClass()`,
`resourceLockOwnerAction()` (a class with `execute($user)`), and the navigation methods (`navigationBadge`, `navigationIcon`,
`navigationLabel`, `pluralLabel`, `navigationGroup`, `navigationSort`, `registerNavigation`).

What differs:

| | kenepa/resource-lock | here |
| --- | --- | --- |
| Table | `resource_locks`, `user_id` foreign key, `morphs('lockable')` | `record_locks` (configurable), string keys (int/UUID/ULID), no foreign keys, unique per record, `expires_at`, `holder` |
| Expiry | `updated_at` + timeout | explicit `expires_at`, extended by the heartbeat |
| Polling | off unless `usesPollingToDetectPresence()` | **on by default** (a heartbeat is what makes the timeout meaningful) |
| Default timeout | 600 s | 120 s |
| Unlocker | everyone, unless `unlockerLimitedAccess()` | **nobody**, unless the gate allows (`unlockerLimitedAccess(false)` restores "everyone") |
| Manager | open to every panel user, unless limited | **closed** until `viewRecordLocks` / `authorizeManager()` (`limitedAccessToResourceLockManager(false)` opens it) |
| Release on leave | Livewire call in `onbeforeunload` (often cancelled by the browser) | `sendBeacon` to a panel route, guarded by user + page-instance token |
| Tab race | the lock belongs to the user | same, plus a per-page token so an old page cannot drop a newer page's lock |
| Taken-over holder | next save fails | notified at the next heartbeat; save refused; stale-write detection |
| Notice | Livewire observer component in `PAGE_START`, custom Blade | Filament modal, driven by the page's own properties; works on modal-edit pages |
| Spatie permission option | a permission name as an alternative to the gate | not built in - call `$user->can()` in `authorizeUnlock()` |
| Extras | | `RecordLockColumn`, `ReleaseLockAction`, `ReleaseLocksBulkAction`, `RecordLockTakenOver` event, `record-lock:prune`, `withRecordLock()` scope |
| Not ported | `UsesResourceLock::resourceLockReturnUrl()` override | override `recordLockReturnUrl()` on the page instead |
| Not ported | views publish tag, `get_resource_lock_owner_action` config key | the notice is a Filament modal (publish the translations to change text); use `ownerUsing()` or `resourceLockOwnerAction()` on the plugin |

## Translations

The interface ships in English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish
under the `filament-record-lock::filament-record-lock` namespace. A test keeps every language in step with the English keys
and placeholders. Override a string by publishing the translations (`--tag=filament-record-lock-translations`) and editing the
copy in `lang/vendor/filament-record-lock`.

## Try it by hand

The repository has a workbench with an `Articles` resource (edit and view pages), a `Notes` resource (UUID keys, modal edit)
and two users. Open it in two browsers, or one normal and one private window:

```bash
composer install
vendor/bin/testbench workbench:build
vendor/bin/testbench filament:assets
vendor/bin/testbench db:seed --class="Workbench\Database\Seeders\DatabaseSeeder"
vendor/bin/testbench serve --host=0.0.0.0
```

Log in at `/admin` as `emma@example.com` / `password` (may unlock and opens "Record locks") in one window and
`noah@example.com` / `password` (an ordinary editor) in the other, then open the same article's edit page in both.

## AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines (`resources/boost/guidelines/core.blade.php`) so a
coding agent wires it up correctly.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
