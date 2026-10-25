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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Filament\Resources\Notes\Pages\ManageNotes;
use Workbench\App\Models\Article;
use Workbench\App\Models\Note;
use Workbench\App\Models\User;

/**
 * Regressions found by the independent review.
 */
class ReviewFixesTest extends TestCase
{
    private Article $article;

    private User $emma;

    private User $noah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->article = Article::query()->create(['title' => 'Launch plan']);
        $this->emma = $this->userNamed('Emma');
        $this->noah = $this->userNamed('Noah');
    }

    private function open(User $user): \Livewire\Features\SupportTesting\Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()]);
    }

    // 1 ----------------------------------------------------------------------------------------------------

    public function test_the_lock_state_cannot_be_changed_from_the_browser(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        foreach (['recordLockActive', 'recordLockBlocked', 'recordLockHolder', 'recordLockId', 'recordLockVersion', 'recordLockCanUnlock', 'recordLockReason', 'recordLockOwner', 'recordLockSince', 'recordLockViewUrl', 'recordLockBackUrl'] as $property) {
            try {
                $page->set($property, 'x');
                $this->fail("[{$property}] must be locked.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_blocked_user_cannot_save_by_flipping_flags(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        try {
            $page->set('recordLockActive', false);
        } catch (CannotUpdateLockedPropertyException) {
        }

        $page->fillForm(['title' => 'Hijack'])->call('save');

        $this->assertSame('Launch plan', $this->article->refresh()->title);
    }

    public function test_the_modal_page_refuses_a_save_even_when_the_lock_was_never_initialised(): void
    {
        $note = Note::query()->create(['title' => 'Agenda']);
        app(RecordLocker::class)->acquire($note, $this->emma, 'p1');

        $this->actingAs($this->noah);
        $page = Livewire::test(ManageNotes::class);
        // The browser sets Filament's own (unlocked) mountedActions without going through mountAction().
        $page->set('mountedActions', [['name' => 'edit', 'arguments' => [], 'context' => ['table' => true, 'recordKey' => $note->getKey()]]]);
        $page->call('callMountedAction');

        $this->assertSame('Agenda', $note->refresh()->title);
    }

    // 2 ----------------------------------------------------------------------------------------------------

    public function test_a_database_that_reports_changed_rows_does_not_make_the_holder_lose_its_own_lock(): void
    {
        $this->travelTo(now()->startOfSecond());
        $locker = new class extends RecordLocker
        {
            protected function conditionalUpdate($query, array $values): int
            {
                parent::conditionalUpdate($query, $values);

                return 0; // MySQL: nothing CHANGED (same second, same values)
            }
        };

        $first = $locker->acquire($this->article, $this->emma, 'p1');
        $again = $locker->acquire($this->article, $this->emma);

        $this->assertNotNull($first);
        $this->assertNotNull($again, 'The holder renewing within the same second must still hold the lock.');
        $this->assertNull($locker->acquire($this->article, $this->noah));
    }

    // 3 ----------------------------------------------------------------------------------------------------

    public function test_eager_loading_binds_string_keys_so_postgres_does_not_compare_varchar_to_integer(): void
    {
        app(RecordLocker::class)->acquire($this->article, $this->emma);
        $bindings = [];
        DB::listen(function ($query) use (&$bindings): void {
            if (str_contains($query->sql, 'record_locks')) {
                $bindings = array_merge($bindings, $query->bindings);
            }
        });

        $loaded = Article::query()->withRecordLock()->get();

        $this->assertNotNull($loaded->first()->recordLock);
        $this->assertContains((string) $this->article->getKey(), $bindings);
        $this->assertNotContains($this->article->getKey(), array_filter($bindings, 'is_int'));
    }

    // 4 ----------------------------------------------------------------------------------------------------

    public function test_the_users_own_save_does_not_make_the_page_stale_later(): void
    {
        config(['filament-record-lock.lock_timeout' => 60]);
        $page = $this->open($this->emma);

        $this->travel(5)->seconds();
        $page->fillForm(['title' => 'Saved by me'])->call('save');

        $this->travel(120)->seconds();
        $page->call('recordLockHeartbeat')
            ->assertSet('recordLockBlocked', false)
            ->assertSet('recordLockReason', null);
    }

    // 5 ----------------------------------------------------------------------------------------------------

    public function test_a_blocked_edit_page_runs_no_header_action(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->callAction('delete');

        $this->assertNotNull(Article::query()->find($this->article->getKey()));
    }

    public function test_the_holder_can_still_run_header_actions(): void
    {
        $page = $this->open($this->emma);

        $page->callAction('delete');

        $this->assertNull(Article::query()->find($this->article->getKey()));
    }

    // 6 ----------------------------------------------------------------------------------------------------

    public function test_a_heartbeat_that_recreates_the_row_stores_the_page_token(): void
    {
        $page = $this->open($this->emma);
        $holder = RecordLock::query()->first()->holder;
        RecordLock::query()->delete();

        $page->call('recordLockHeartbeat');

        $this->assertSame($holder, RecordLock::query()->first()->holder);
    }

    public function test_a_heartbeat_does_not_steal_the_token_from_a_newer_tab(): void
    {
        $older = $this->open($this->emma);
        $this->open($this->emma);
        $newer = RecordLock::query()->first()->holder;

        $older->call('recordLockHeartbeat');

        $this->assertSame($newer, RecordLock::query()->first()->holder);
    }

    // 7 ----------------------------------------------------------------------------------------------------

    public function test_the_manager_is_unavailable_on_a_tenant_panel_without_a_scope_hook(): void
    {
        Gate::define('viewRecordLocks', fn (): bool => true);
        $this->actingAs($this->emma);
        $panel = Filament::getCurrentPanel();
        $plugin = $panel->getPlugin(RecordLockPlugin::ID);

        $this->assertTrue(RecordLockResource::canViewAny());

        $panel->tenant(User::class);

        $this->assertFalse(RecordLockResource::canViewAny());
        $this->assertFalse(RecordLockResource::isScopedToTenant());

        $plugin->scopeManagerQuery(fn (Builder $query) => $query->where('user_id', (string) $this->emma->getKey()));

        $this->assertTrue(RecordLockResource::canViewAny());
        app(RecordLocker::class)->acquire($this->article, $this->emma);
        app(RecordLocker::class)->acquire(Article::query()->create(['title' => 'B']), $this->noah);
        $this->assertSame(1, RecordLockResource::getEloquentQuery()->count());
    }

    // 8 ----------------------------------------------------------------------------------------------------

    public function test_take_over_is_one_step_and_survives_the_old_holder_reinserting(): void
    {
        $locker = app(RecordLocker::class);
        $locker->acquire($this->article, $this->emma, 'p1');

        $lock = $locker->takeOver($this->article, $this->noah, 'p2');

        $this->assertSame((string) $this->noah->getKey(), $lock->user_id);
        $this->assertSame(1, RecordLock::query()->count());
        // The old holder's heartbeat right after must not win nor throw.
        $this->assertNull($locker->acquire($this->article, $this->emma));
    }

    public function test_take_over_of_a_record_nobody_holds_just_locks_it(): void
    {
        $lock = app(RecordLocker::class)->takeOver($this->article, $this->noah, 'p2');

        $this->assertSame((string) $this->noah->getKey(), $lock->user_id);
    }

    // 9 ----------------------------------------------------------------------------------------------------

    public function test_the_manager_survives_a_lock_of_an_unknown_type(): void
    {
        Gate::define('viewRecordLocks', fn (): bool => true);
        Gate::define('unlockRecordLock', fn (): bool => true);
        $this->actingAs($this->emma);
        $lock = RecordLock::query()->create([
            'lockable_type' => 'Gone\\Missing\\Model',
            'lockable_id' => '7',
            'user_id' => (string) $this->noah->getKey(),
            'locked_at' => now(),
            'expires_at' => now()->addMinute(),
        ]);

        Livewire::test(ListRecordLocks::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$lock])
            ->callTableAction('releaseLock', $lock);

        $this->assertSame(0, RecordLock::query()->count());
    }

    // 10 ---------------------------------------------------------------------------------------------------

    public function test_the_poll_element_is_pinned_to_the_viewport_so_visible_polling_never_pauses(): void
    {
        config(['filament-record-lock.polling.visible' => true]);

        $this->open($this->emma)->assertSeeHtml('position: fixed');
    }
}
