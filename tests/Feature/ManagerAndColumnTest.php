<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Resources\RecordLockResource;
use Asignua\FilamentRecordLock\Resources\RecordLockResource\Pages\ListRecordLocks;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\Pages\ListArticles;
use Workbench\App\Models\Article;
use Workbench\App\Models\User;

class ManagerAndColumnTest extends TestCase
{
    private User $emma;

    private User $noah;

    private Article $first;

    private Article $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emma = $this->userNamed('Emma');
        $this->noah = $this->userNamed('Noah');
        $this->first = Article::query()->create(['title' => 'First']);
        $this->second = Article::query()->create(['title' => 'Second']);

        app(RecordLocker::class)->acquire($this->first, $this->emma, 'p1');
        app(RecordLocker::class)->acquire($this->second, $this->noah, 'p2');
    }

    private function admin(): void
    {
        Gate::define('viewRecordLocks', fn (User $user): bool => $user->is($this->emma));
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->emma));
        $this->actingAs($this->emma);
    }

    // ---- the manager ------------------------------------------------------------------------------------

    public function test_the_manager_is_closed_without_the_gate(): void
    {
        $this->actingAs($this->emma);

        $this->assertFalse(RecordLockResource::canViewAny());
        Livewire::test(ListRecordLocks::class)->assertForbidden();
    }

    public function test_the_manager_is_open_to_the_gate_and_lists_active_locks(): void
    {
        $this->admin();

        $expired = Article::query()->create(['title' => 'Old']);
        config(['filament-record-lock.lock_timeout' => 1]);
        app(RecordLocker::class)->acquire($expired, $this->noah);
        config(['filament-record-lock.lock_timeout' => 120]);
        $this->travel(5)->seconds();
        app(RecordLocker::class)->acquire($this->first, $this->emma);
        app(RecordLocker::class)->acquire($this->second, $this->noah);

        $this->assertTrue(RecordLockResource::canViewAny());

        $expiredLock = app(RecordLocker::class)->find($expired);

        Livewire::test(ListRecordLocks::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([
                app(RecordLocker::class)->find($this->first),
                app(RecordLocker::class)->find($this->second),
            ])
            ->assertCanNotSeeTableRecords([$expiredLock])
            ->assertSee('First')
            ->assertSee('Emma')
            ->filterTable('active', false)
            ->assertCanSeeTableRecords([$expiredLock]);
    }

    public function test_the_manager_is_read_only(): void
    {
        $this->admin();
        $lock = app(RecordLocker::class)->find($this->first);

        $this->assertFalse(RecordLockResource::canCreate());
        $this->assertFalse(RecordLockResource::canEdit($lock));
        $this->assertFalse(RecordLockResource::canDelete($lock));
        $this->assertFalse(RecordLockResource::canDeleteAny());
    }

    public function test_the_manager_can_be_switched_off(): void
    {
        $plugin = Filament::getCurrentPanel()->getPlugin(RecordLockPlugin::ID);

        $this->assertTrue($plugin->isManagerEnabled());
        $plugin->manager(false);
        $this->assertFalse($plugin->isManagerEnabled());
    }

    public function test_a_lock_is_released_from_the_manager_by_someone_who_may_unlock(): void
    {
        $this->admin();
        $lock = app(RecordLocker::class)->find($this->second);

        Livewire::test(ListRecordLocks::class)
            ->assertTableActionVisible('releaseLock', $lock)
            ->callTableAction('releaseLock', $lock);

        $this->assertNull(app(RecordLocker::class)->active($this->second));
        $this->assertNotNull(app(RecordLocker::class)->active($this->first));
    }

    public function test_release_is_hidden_and_refused_for_a_viewer_without_the_unlock_permission(): void
    {
        Gate::define('viewRecordLocks', fn (User $user): bool => true);
        $this->actingAs($this->emma);
        $lock = app(RecordLocker::class)->find($this->second);

        Livewire::test(ListRecordLocks::class)
            ->assertTableActionHidden('releaseLock', $lock)
            ->assertTableActionHidden('releaseAll');

        $this->assertNotNull(app(RecordLocker::class)->active($this->second));
    }

    public function test_selected_locks_are_released_in_bulk(): void
    {
        $this->admin();
        $locks = RecordLock::query()->get();

        Livewire::test(ListRecordLocks::class)
            ->callTableBulkAction('releaseLocks', $locks);

        $this->assertSame(0, RecordLock::query()->count());
    }

    public function test_release_all_deletes_every_lock(): void
    {
        $this->admin();

        Livewire::test(ListRecordLocks::class)->callTableAction('releaseAll');

        $this->assertSame(0, RecordLock::query()->count());
    }

    public function test_unlocker_limited_access_off_lets_everyone_release(): void
    {
        $this->actingAs($this->noah);
        $plugin = Filament::getCurrentPanel()->getPlugin(RecordLockPlugin::ID);

        $this->assertFalse($plugin->canUnlock($this->first));

        $plugin->unlockerLimitedAccess(false);
        $this->assertTrue($plugin->canUnlock($this->first));
    }

    public function test_authorize_unlock_takes_a_closure_that_gets_the_record(): void
    {
        $this->actingAs($this->noah);
        $plugin = Filament::getCurrentPanel()->getPlugin(RecordLockPlugin::ID);

        $plugin->authorizeUnlock(fn (?Article $record): bool => $record?->title === 'First');

        $this->assertTrue($plugin->canUnlock($this->first));
        $this->assertFalse($plugin->canUnlock($this->second));
    }

    // ---- the table column and the actions on a resource table ---------------------------------------------

    public function test_the_column_marks_locked_rows_and_names_the_holder(): void
    {
        $this->actingAs($this->noah);

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords([$this->first, $this->second])
            ->assertTableColumnStateSet('lock', 'other', $this->first)
            ->assertTableColumnStateSet('lock', 'mine', $this->second)
            ->assertSee('Emma is editing this record')
            ->assertSee(__('filament-record-lock::filament-record-lock.column.mine'));
    }

    public function test_a_free_row_shows_no_lock(): void
    {
        $free = Article::query()->create(['title' => 'Free']);
        $this->actingAs($this->noah);

        Livewire::test(ListArticles::class)->assertTableColumnStateSet('lock', null, $free);
    }

    public function test_the_column_eager_loads_locks_with_a_scope_so_rows_do_not_cost_a_query_each(): void
    {
        foreach (range(1, 6) as $i) {
            app(RecordLocker::class)->acquire(Article::query()->create(['title' => "Row {$i}"]), $this->emma);
        }

        $this->actingAs($this->noah);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'record_locks')) {
                $queries[] = $query->sql;
            }
        });

        Livewire::test(ListArticles::class)->assertSee('Row 6');

        $this->assertLessThanOrEqual(2, count($queries), implode("\n", $queries));
    }

    public function test_the_record_action_releases_the_lock_of_a_row_for_someone_who_may(): void
    {
        $this->admin();

        Livewire::test(ListArticles::class)
            ->assertTableActionVisible('releaseLock', $this->second)
            ->callTableAction('releaseLock', $this->second);

        $this->assertNull(app(RecordLocker::class)->active($this->second));
    }

    public function test_the_record_action_is_hidden_on_a_free_row_and_for_users_who_may_not(): void
    {
        $free = Article::query()->create(['title' => 'Free']);
        $this->admin();

        Livewire::test(ListArticles::class)->assertTableActionHidden('releaseLock', $free);

        $this->actingAs($this->noah);

        Livewire::test(ListArticles::class)->assertTableActionHidden('releaseLock', $this->first);
    }
}
