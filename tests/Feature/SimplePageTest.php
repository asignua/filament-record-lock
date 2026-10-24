<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Support\RecordLocker;
use Asignua\FilamentRecordLock\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Notes\Pages\ManageNotes;
use Workbench\App\Models\Note;
use Workbench\App\Models\User;

/**
 * The modal-edit variant, on a model with a UUID key.
 */
class SimplePageTest extends TestCase
{
    private Note $note;

    private User $emma;

    private User $noah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->note = Note::query()->create(['title' => 'Standup agenda']);
        $this->emma = $this->userNamed('Emma');
        $this->noah = $this->userNamed('Noah');
    }

    private function open(User $user): Testable
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return Livewire::test(ManageNotes::class)
            ->mountTableAction('edit', $this->note->getKey());
    }

    public function test_opening_the_edit_modal_locks_the_record(): void
    {
        $page = $this->open($this->emma);

        $page->assertSet('recordLockActive', true)->assertSet('recordLockBlocked', false);
        $this->assertTrue(app(RecordLocker::class)->heldBy($this->note, $this->emma));
    }

    public function test_a_second_user_is_blocked_and_cannot_save(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->assertSet('recordLockBlocked', true)
            ->assertSet('recordLockOwner', 'Emma')
            ->assertSee(__('filament-record-lock::filament-record-lock.modal.back'));

        $page->callMountedTableAction();

        Notification::assertNotified(__('filament-record-lock::filament-record-lock.notifications.save_refused_title'));
        $this->assertSame('Standup agenda', $this->note->refresh()->title);
    }

    public function test_the_holder_saves_and_the_lock_is_released_with_the_modal(): void
    {
        $page = $this->open($this->emma);

        $page->setTableActionData(['title' => 'Renamed'])->callMountedTableAction();

        $this->assertSame('Renamed', $this->note->refresh()->title);
        $this->assertNull(app(RecordLocker::class)->active($this->note));
    }

    public function test_closing_the_modal_releases_the_lock(): void
    {
        $page = $this->open($this->emma);

        $page->unmountTableAction();

        $this->assertNull(app(RecordLocker::class)->active($this->note));
        $page->assertSet('recordLockActive', false);
    }

    public function test_dismissing_the_notice_closes_the_modal_and_keeps_the_other_users_lock(): void
    {
        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->call('recordLockDismiss');

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->note, $this->emma));
    }

    public function test_taking_over_in_the_modal_needs_the_gate(): void
    {
        Gate::define('unlockRecordLock', fn (User $user): bool => $user->is($this->noah));

        $this->open($this->emma);
        $page = $this->open($this->noah);

        $page->call('recordLockTakeOver')->assertSet('recordLockBlocked', false);

        $this->assertTrue(app(RecordLocker::class)->heldBy($this->note, $this->noah));
    }
}
