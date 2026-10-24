<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\RecordLockPlugin;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Models\Article;
use Workbench\App\Models\User;

class EditPageTest extends TestCase
{
    private Article $article;

    private User $emma;

    private User $noah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->article = Article::query()->create(['title' => 'Launch plan', 'body' => 'Draft']);
        $this->emma = $this->userNamed('Emma');
        $this->noah = $this->userNamed('Noah');
    }

    private function open(User $user): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()]);
    }

    public function test_opening_the_page_takes_the_lock(): void
    {
        $page = $this->open($this->emma);

        $page->assertSet('recordLockActive', true)
            ->assertSet('recordLockBlocked', false)
            ->assertSet('recordLockReason', null)
            ->assertNotSet('recordLockId', null)
            ->assertNotSet('recordLockHolder', null);

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->emma));
    }

    public function test_a_second_user_is_blocked_and_told_who_holds_it(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockReason', 'locked')
            ->assertSet('recordLockOwner', 'Emma')
            ->assertNotSet('recordLockSince', null)
            ->assertSet('recordLockId', null);

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->emma));
    }

    public function test_the_owner_can_be_hidden(): void
    {
        config(['filament-record-lock.lock_notice.display_owner' => false]);

        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockOwner', null)
            ->assertDontSee('Emma');
    }

    public function test_the_notice_offers_the_read_only_page_and_the_list(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->assertSet('recordLockViewUrl', url('/admin/articles/'.$this->article->getRouteKey()))
            ->assertSet('recordLockBackUrl', url('/admin/articles'));
    }

    public function test_there_is_no_read_only_link_for_a_user_who_may_not_view_the_record(): void
    {
        $this->open($this->emma);
        $this->actingAs($this->noah);

        // A user who may not view the record is not offered the page (Gate policy on the model).
        Gate::policy(Article::class, ViewDeniedPolicy::class);

        Livewire::test(EditArticle::class, ['record' => $this->article->getRouteKey()])
            ->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockViewUrl', null);
    }

    public function test_unlock_and_edit_is_not_offered_without_the_gate(): void
    {
        $this->open($this->emma);

        $this->open($this->noah)->assertSet('recordLockCanUnlock', false);
    }

    public function test_unlock_and_edit_is_offered_to_users_the_gate_allows(): void
    {
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->noah));

        $this->open($this->emma);

        $this->open($this->noah)->assertSet('recordLockCanUnlock', true);
    }

    public function test_the_notice_markup_carries_the_translated_texts_and_the_buttons(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->assertSee(__('filament-record-lock::filament-record-lock.modal.heading_locked'))
            ->assertSee(__('filament-record-lock::filament-record-lock.modal.unlock'))
            ->assertSeeHtml('wire:click="recordLockTakeOver"')
            ->assertSeeHtml('wire:click="recordLockReload"')
            ->assertSee('recordLockCanUnlock', false)
            ->assertSee('x-bind:href="$wire.recordLockViewUrl"', false)
            ->assertSeeHtml('x-text="title"')
            ->assertSeeHtml('x-text="description"');
    }

    public function test_taking_over_without_the_permission_does_nothing(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->call('recordLockTakeOver')
            ->assertSet('recordLockBlocked', true);

        Notification::assertNotified(__('filament-record-lock::filament-record-lock.notifications.unlock_denied'));
        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->emma));
    }

    public function test_taking_over_with_the_permission_swaps_the_holder_and_refreshes_the_form(): void
    {
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->noah));

        $emmasPage = $this->open($this->emma);
        $noahsPage = $this->open($this->noah);

        $this->article->update(['title' => 'Changed meanwhile']);

        $noahsPage->call('recordLockTakeOver')
            ->assertSet('recordLockBlocked', false)
            ->assertSet('recordLockReason', null)
            ->assertSet('data.title', 'Changed meanwhile');

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->noah));

        // The previous holder learns it on the next heartbeat and is told why.
        $this->actingAs($this->emma);
        $emmasPage->call('recordLockHeartbeat')
            ->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockReason', 'taken_over')
            ->assertSet('recordLockOwner', 'Noah');

        Notification::assertNotified(__('filament-record-lock::filament-record-lock.notifications.taken_over_title'));
    }

    public function test_a_save_by_the_user_who_lost_the_lock_is_refused(): void
    {
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->noah));

        $emmasPage = $this->open($this->emma);
        $this->open($this->noah)->call('recordLockTakeOver');

        $this->actingAs($this->emma);
        $emmasPage->fillForm(['title' => 'Emma overwrites'])->call('save');

        $this->assertSame('Launch plan', $this->article->refresh()->title);
        Notification::assertNotified(__('filament-record-lock::filament-record-lock.notifications.save_refused_title'));
        $emmasPage->assertSet('recordLockBlocked', true);
    }

    public function test_a_save_by_the_holder_goes_through(): void
    {
        $page = $this->open($this->emma);

        $page->fillForm(['title' => 'Saved'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Saved', $this->article->refresh()->title);
        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->emma), 'The user is still on the page.');
    }

    public function test_the_save_check_can_be_turned_off(): void
    {
        config(['filament-record-lock.check_locks_before_saving' => false]);
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->noah));

        $emmasPage = $this->open($this->emma);
        $this->open($this->noah)->call('recordLockTakeOver');

        $this->actingAs($this->emma);
        $emmasPage->fillForm(['title' => 'Last write wins'])->call('save');

        $this->assertSame('Last write wins', $this->article->refresh()->title);
    }

    public function test_the_heartbeat_extends_the_lock(): void
    {
        $page = $this->open($this->emma);
        $before = RecordLock::query()->first()->expires_at;

        $this->travel(30)->seconds();
        $page->call('recordLockHeartbeat')->assertSet('recordLockBlocked', false);

        $this->assertTrue(RecordLock::query()->first()->expires_at->greaterThan($before));
    }

    public function test_a_lapsed_lock_nobody_took_is_regained_by_the_heartbeat(): void
    {
        config(['filament-record-lock.lock_timeout' => 60]);
        $page = $this->open($this->emma);

        $this->travel(120)->seconds();
        $page->call('recordLockHeartbeat')->assertSet('recordLockBlocked', false);

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->emma));
    }

    public function test_a_change_saved_while_the_lock_had_lapsed_marks_the_page_stale(): void
    {
        config(['filament-record-lock.lock_timeout' => 60]);
        $page = $this->open($this->emma);

        $this->travel(120)->seconds();
        $this->article->update(['title' => 'Someone else was here']);

        $page->call('recordLockHeartbeat')
            ->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockReason', 'stale');

        $page->fillForm(['title' => 'Stale overwrite'])->call('save');
        $this->assertSame('Someone else was here', $this->article->refresh()->title);

        $page->call('recordLockReload')
            ->assertSet('recordLockBlocked', false)
            ->assertSet('data.title', 'Someone else was here');
    }

    public function test_a_blocked_page_picks_the_record_up_when_the_holder_leaves(): void
    {
        $emmasPage = $this->open($this->emma);
        $noahsPage = $this->open($this->noah);

        $noahsPage->call('recordLockHeartbeat')->assertSet('recordLockBlocked', true);

        $this->actingAs($this->emma);
        app(RecordLocker::class)->release($this->article, $this->emma);
        $this->article->update(['title' => 'Emma finished']);

        $this->actingAs($this->noah);
        $noahsPage->call('recordLockHeartbeat')
            ->assertSet('recordLockBlocked', false)
            ->assertSet('data.title', 'Emma finished');

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->article, $this->noah));
        unset($emmasPage);
    }

    public function test_the_page_renders_the_heartbeat_and_the_leave_beacon(): void
    {
        $page = $this->open($this->emma);

        $page->assertSeeHtml('wire:poll.15s="recordLockHeartbeat"')
            ->assertSeeHtml('navigator.sendBeacon')
            ->assertSeeHtml('admin\\/record-lock\\/release');
    }

    public function test_the_heartbeat_is_configurable_and_can_be_switched_off(): void
    {
        config(['filament-record-lock.polling' => ['enabled' => true, 'interval' => 7, 'keep_alive' => true, 'visible' => true]]);

        $this->open($this->emma)->assertSeeHtml('wire:poll.keep-alive.visible.7s="recordLockHeartbeat"');

        config(['filament-record-lock.polling.enabled' => false]);

        $this->open($this->emma)->assertDontSeeHtml('recordLockHeartbeat');
    }

    public function test_the_plugin_options_override_the_config(): void
    {
        $plugin = Filament::getCurrentPanel()->getPlugin(RecordLockPlugin::ID);

        $this->assertSame(120, $plugin->getLockTimeout());
        $this->assertSame(15, $plugin->getPresencePollingInterval());
        $this->assertTrue($plugin->shouldCheckLocksBeforeSaving());

        $plugin->lockTimeout(300)->presencePollingInterval(5)->checkLocksBeforeSaving(false);

        $this->assertSame(300, $plugin->getLockTimeout());
        $this->assertSame(5, $plugin->getPresencePollingInterval());
        $this->assertFalse($plugin->shouldCheckLocksBeforeSaving());
    }
}

class ViewDeniedPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return false;
    }
}
