<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Events\RecordLockChanged;
use Asignua\FilamentRecordLock\Support\Realtime;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Articles\Pages\EditArticle;
use Workbench\App\Models\Article;
use Workbench\App\Models\User;

class RealtimeTest extends TestCase
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

    private function on(): void
    {
        config(['filament-record-lock.realtime.enabled' => true]);
    }

    public function test_enabled_resolution(): void
    {
        config(['broadcasting.default' => 'log', 'filament-record-lock.realtime.enabled' => null]);
        $this->assertFalse(Realtime::enabled());

        config(['broadcasting.default' => 'reverb']);
        $this->assertTrue(Realtime::enabled());

        config(['broadcasting.default' => 'pusher']);
        $this->assertTrue(Realtime::enabled());

        config(['filament-record-lock.realtime.enabled' => false]);
        $this->assertFalse(Realtime::enabled());

        config(['broadcasting.default' => 'log', 'filament-record-lock.realtime.enabled' => true]);
        $this->assertTrue(Realtime::enabled());

        config(['filament-record-lock.realtime.enabled' => 'false']);
        $this->assertFalse(Realtime::enabled());

        // A named connection decides through its driver.
        config([
            'filament-record-lock.realtime.enabled' => null,
            'filament-record-lock.realtime.connection' => 'ws',
            'broadcasting.connections.ws' => ['driver' => 'reverb'],
        ]);
        $this->assertTrue(Realtime::enabled());
    }

    public function test_nothing_is_broadcast_when_disabled(): void
    {
        config(['filament-record-lock.realtime.enabled' => false]);
        Event::fake([RecordLockChanged::class]);

        $locker = app(RecordLocker::class);
        $locker->acquire($this->article, $this->emma, 'p1');
        $locker->release($this->article, $this->emma);
        $locker->acquire($this->article, $this->emma, 'p1');
        $locker->takeOver($this->article, $this->noah, 'p2');
        $locker->forceRelease($this->article);

        Event::assertNotDispatched(RecordLockChanged::class);
    }

    public function test_release_broadcasts_only_when_a_lock_was_removed(): void
    {
        $this->on();
        Event::fake([RecordLockChanged::class]);
        $locker = app(RecordLocker::class);

        // Nothing to release, and acquiring alone says nothing.
        $locker->release($this->article, $this->emma);
        $locker->acquire($this->article, $this->emma, 'p1');
        Event::assertNotDispatched(RecordLockChanged::class);

        $locker->release($this->article, $this->emma);

        Event::assertDispatchedTimes(RecordLockChanged::class, 1);
        Event::assertDispatched(
            RecordLockChanged::class,
            fn (RecordLockChanged $e): bool => $e->reason === RecordLockChanged::REASON_RELEASED
                && $e->key === (string) $this->article->getKey()
                && $e->type === $this->article->getMorphClass(),
        );
    }

    public function test_force_release_broadcasts(): void
    {
        $this->on();
        $locker = app(RecordLocker::class);
        $locker->acquire($this->article, $this->emma, 'p1');
        Event::fake([RecordLockChanged::class]);

        $locker->forceRelease($this->article);

        Event::assertDispatchedTimes(RecordLockChanged::class, 1);
    }

    public function test_takeover_broadcasts_the_new_holder_and_the_old_event_still_fires(): void
    {
        $this->on();
        $locker = app(RecordLocker::class);
        $locker->acquire($this->article, $this->emma, 'p1');
        Event::fake([RecordLockChanged::class, \Asignua\FilamentRecordLock\Events\RecordLockTakenOver::class]);

        $locker->takeOver($this->article, $this->noah, 'p2');

        Event::assertDispatched(\Asignua\FilamentRecordLock\Events\RecordLockTakenOver::class);
        Event::assertDispatched(
            RecordLockChanged::class,
            fn (RecordLockChanged $e): bool => $e->reason === RecordLockChanged::REASON_TAKEN_OVER
                && (string) $e->holder === (string) $this->noah->getKey(),
        );
    }

    public function test_taking_over_a_free_record_does_not_broadcast(): void
    {
        $this->on();
        Event::fake([RecordLockChanged::class]);

        app(RecordLocker::class)->takeOver($this->article, $this->noah, 'p2');

        Event::assertNotDispatched(RecordLockChanged::class);
    }

    public function test_the_leave_beacon_broadcasts(): void
    {
        $this->on();
        $lock = app(RecordLocker::class)->acquire($this->article, $this->emma, 'page-1');
        Event::fake([RecordLockChanged::class]);

        $this->actingAs($this->emma)
            ->post('/admin/record-lock/release', ['lock' => $lock->getKey(), 'holder' => 'page-1'])
            ->assertNoContent();

        Event::assertDispatchedTimes(RecordLockChanged::class, 1);
    }

    public function test_the_event_goes_to_a_private_channel_of_the_record_with_a_minimal_payload(): void
    {
        $event = new RecordLockChanged('App\\Models\\Article', '42', RecordLockChanged::REASON_RELEASED, 7);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-'.Realtime::channelFor('App\\Models\\Article', '42'), $channels[0]->name);
        $this->assertStringNotContainsString('\\', Realtime::channelFor('App\\Models\\Article', '42'));
        $this->assertSame('filament-record-lock.changed', $event->broadcastAs());
        $this->assertSame(['type', 'key', 'reason', 'holder'], array_keys($event->broadcastWith()));
    }

    public function test_channel_names_are_distinct_per_record_and_have_no_dots_in_segments(): void
    {
        $a = Realtime::channelFor('A', '1.2');
        $this->assertCount(3, explode('.', $a));
        $this->assertNotSame($a, Realtime::channelFor('A', '1.3'));
    }

    public function test_channel_auth_allows_a_user_who_may_edit_the_record(): void
    {
        $type = $this->segment($this->article->getMorphClass());
        $key = $this->segment((string) $this->article->getKey());
        $this->actingAs($this->noah);

        $this->assertTrue(Realtime::authorize($this->noah, $type, $key));
    }

    public function test_channel_auth_refuses_a_user_the_policy_denies(): void
    {
        $this->actingAs($this->noah);
        Gate::policy(Article::class, UpdateDeniedPolicy::class);

        $this->assertFalse(Realtime::authorize(
            $this->noah,
            $this->segment($this->article->getMorphClass()),
            $this->segment((string) $this->article->getKey()),
        ));
    }

    public function test_channel_auth_refuses_unknown_missing_and_non_lockable_targets(): void
    {
        $key = $this->segment((string) $this->article->getKey());

        $this->assertFalse(Realtime::authorize($this->noah, $this->segment(Article::class), $this->segment('9999')));
        $this->assertFalse(Realtime::authorize($this->noah, $this->segment('Nope\\Missing'), $key));
        $this->assertFalse(Realtime::authorize($this->noah, $this->segment(User::class), $this->segment((string) $this->noah->getKey())));
        $this->assertFalse(Realtime::authorize($this->noah, '!!!', $key));
    }

    public function test_the_page_listens_only_when_enabled_and_the_listener_runs_the_heartbeat(): void
    {
        $event = Realtime::listener($this->article);

        config(['filament-record-lock.realtime.enabled' => false]);
        $page = $this->open($this->emma);
        $this->assertArrayNotHasKey($event, $this->listenersOf($page));

        $this->on();
        $this->open($this->emma);
        $waiting = $this->open($this->noah)->assertSet('recordLockBlocked', true);
        $this->assertSame('recordLockHeartbeat', $this->listenersOf($waiting)[$event] ?? null);

        // The holder leaves; the pushed event makes the waiting page reload at once (no poll).
        app(RecordLocker::class)->forceRelease($this->article);

        $waiting->dispatch($event)->assertSet('recordLockBlocked', false)->assertNotSet('recordLockId', null);
    }

    public function test_a_pushed_takeover_blocks_the_old_holder_at_once(): void
    {
        $this->on();
        $event = Realtime::listener($this->article);
        $old = $this->open($this->emma);

        app(RecordLocker::class)->takeOver($this->article, $this->noah, 'other');

        $old->dispatch($event)->assertSet('recordLockBlocked', true)->assertSet('recordLockReason', 'taken_over');
    }

    /**
     * @return array<string, string>
     */
    private function listenersOf(Testable $page): array
    {
        $component = $page->instance();
        $method = new \ReflectionMethod($component, 'getListeners');

        return $method->invoke($component);
    }

    private function segment(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

class UpdateDeniedPolicy
{
    public function update(User $user, Article $article): bool
    {
        return false;
    }
}
