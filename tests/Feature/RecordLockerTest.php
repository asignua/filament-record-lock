<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Events\RecordLockTakenOver;
use Asignua\FilamentRecordLock\Models\RecordLock;
use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Workbench\App\Models\Article;
use Workbench\App\Models\Note;
use Workbench\App\Models\Ticket;

class RecordLockerTest extends TestCase
{
    private RecordLocker $locker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locker = app(RecordLocker::class);
    }

    private function article(): Article
    {
        return Article::query()->create(['title' => 'A']);
    }

    public function test_a_free_record_is_acquired_and_blocks_another_user(): void
    {
        $article = $this->article();
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');

        $lock = $this->locker->acquire($article, $emma, 'page-1');

        $this->assertNotNull($lock);
        $this->assertSame((string) $emma->getKey(), $lock->user_id);
        $this->assertTrue($this->locker->heldBy($article, $emma));
        $this->assertTrue($this->locker->lockedByOther($article, $noah));
        $this->assertNull($this->locker->acquire($article, $noah, 'page-2'));
        $this->assertSame(1, RecordLock::query()->count(), 'One row per record.');
    }

    public function test_the_same_user_renews_without_a_second_row_and_keeps_the_holder_on_a_heartbeat(): void
    {
        $article = $this->article();
        $emma = $this->userNamed('Emma');

        $first = $this->locker->acquire($article, $emma, 'page-1');
        $this->travel(60)->seconds();
        $renewed = $this->locker->acquire($article, $emma);

        $this->assertNotNull($first);
        $this->assertNotNull($renewed);
        $this->assertSame($first->getKey(), $renewed->getKey());
        $this->assertSame('page-1', $renewed->holder);
        $this->assertTrue($renewed->expires_at->greaterThan($first->expires_at));
        $this->assertSame(1, RecordLock::query()->count());
    }

    public function test_a_lock_expires_without_a_heartbeat_and_can_then_be_taken(): void
    {
        config(['filament-record-lock.lock_timeout' => 60]);
        $article = $this->article();
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');

        $this->locker->acquire($article, $emma, 'page-1');
        $this->travel(59)->seconds();
        $this->assertNull($this->locker->acquire($article, $noah, 'page-2'));

        $this->travel(2)->seconds();
        $this->assertNull($this->locker->active($article), 'Expired means not active.');

        $lock = $this->locker->acquire($article, $noah, 'page-2');

        $this->assertNotNull($lock);
        $this->assertSame((string) $noah->getKey(), $lock->user_id);
        $this->assertSame('page-2', $lock->holder);
        $this->assertSame(1, RecordLock::query()->count());
    }

    public function test_release_needs_the_owner_and_the_matching_page_instance(): void
    {
        $article = $this->article();
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');

        $this->locker->acquire($article, $emma, 'page-1');

        $this->assertFalse($this->locker->release($article, $noah));
        $this->assertFalse($this->locker->release($article, $emma, 'another-tab'));
        $this->assertTrue($this->locker->heldBy($article, $emma));

        $this->assertTrue($this->locker->release($article, $emma, 'page-1'));
        $this->assertNull($this->locker->find($article));
    }

    public function test_force_release_drops_any_lock(): void
    {
        $article = $this->article();
        $this->locker->acquire($article, $this->userNamed('Emma'));

        $this->assertTrue($this->locker->forceRelease($article));
        $this->assertNull($this->locker->find($article));
        $this->assertFalse($this->locker->forceRelease($article));
    }

    public function test_take_over_replaces_the_holder_and_fires_an_event(): void
    {
        Event::fake([RecordLockTakenOver::class]);
        $article = $this->article();
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');

        $this->locker->acquire($article, $emma, 'page-1');
        $lock = $this->locker->takeOver($article, $noah, 'page-2');

        $this->assertSame((string) $noah->getKey(), $lock->user_id);
        $this->assertTrue($this->locker->heldBy($article, $noah));
        $this->assertFalse($this->locker->heldBy($article, $emma));

        Event::assertDispatched(
            RecordLockTakenOver::class,
            fn (RecordLockTakenOver $event): bool => $event->record->is($article)
                && $event->previousHolder?->getKey() === $emma->getKey()
                && $event->newHolder->getAuthIdentifier() === $noah->getKey(),
        );
    }

    public function test_taking_over_your_own_lock_fires_no_event(): void
    {
        Event::fake([RecordLockTakenOver::class]);
        $article = $this->article();
        $emma = $this->userNamed('Emma');

        $this->locker->acquire($article, $emma, 'page-1');
        $this->locker->takeOver($article, $emma, 'page-2');

        Event::assertNotDispatched(RecordLockTakenOver::class);
    }

    public function test_integer_uuid_and_ulid_keys_are_all_locked(): void
    {
        $emma = $this->userNamed('Emma');
        $noah = $this->userNamed('Noah');

        $records = [
            $this->article(),
            Note::query()->create(['title' => 'N']),
            Ticket::query()->create(['title' => 'T']),
        ];

        $this->assertIsString($records[1]->getKey());
        $this->assertSame(36, strlen($records[1]->getKey()));
        $this->assertSame(26, strlen($records[2]->getKey()));

        foreach ($records as $record) {
            $this->assertNotNull($this->locker->acquire($record, $emma), $record::class);
            $this->assertNull($this->locker->acquire($record, $noah), $record::class);
            $this->assertTrue($this->locker->lockedByOther($record, $noah));
            $this->assertSame($record->getKey(), $this->locker->find($record)?->lockable?->getKey());
        }

        $this->assertSame(3, RecordLock::query()->count());
    }

    public function test_the_prune_command_deletes_only_expired_locks(): void
    {
        config(['filament-record-lock.lock_timeout' => 60]);
        $emma = $this->userNamed('Emma');
        $old = $this->article();
        $fresh = $this->article();

        $this->locker->acquire($old, $emma);
        $this->travel(120)->seconds();
        $this->locker->acquire($fresh, $emma);

        $this->artisan('record-lock:prune')->assertSuccessful();

        $this->assertNull($this->locker->find($old));
        $this->assertNotNull($this->locker->find($fresh));
    }

    public function test_the_prune_command_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'record-lock:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('*/10 * * * *', $events->first()->expression);
    }
}
