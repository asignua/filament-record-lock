<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Resources\Pages\Concerns;

use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

use function Livewire\store;

/**
 * For an Edit page (`EditRecord`): the record is locked while the page is open.
 *
 *     class EditArticle extends EditRecord
 *     {
 *         use UsesResourceLock;
 *     }
 *
 * The model needs {@see \Asignua\FilamentRecordLock\Models\Concerns\HasLocks}. The name is kenepa/resource-lock's.
 */
// @phpstan-ignore-next-line trait.unused (used by the host's pages; the tests exercise it)
trait UsesResourceLock
{
    use UsesLocks;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->initializeRecordLock();
    }

    protected function recordLockRecord(): ?Model
    {
        return $this->getRecord();
    }

    protected function recordLockRefresh(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
    }

    /**
     * Actions that may run while the lock is lost (read-only ones); everything else - Delete, Replicate, your own
     * header actions, actions inside the form - is refused like a save. Relation managers are separate Livewire
     * components and are NOT covered: guard their writes with the model policy.
     *
     * @return list<string>
     */
    protected function recordLockAllowedActions(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function callMountedAction(array $arguments = []): mixed
    {
        $name = $this->getMountedAction()?->getName();

        if (!in_array($name, $this->recordLockAllowedActions(), true) && !$this->recordLockPermitsSave()) {
            return null;
        }

        return parent::callMountedAction($arguments);
    }

    /**
     * A save is refused for a user who no longer holds the lock. When it goes through and the page leaves for
     * another URL, the lock is released right away instead of waiting for the leave beacon.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if (!$this->recordLockPermitsSave()) {
            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        // Our own write moved `updated_at`; remember it, or a lapse of the lock would call our save "someone else's".
        $this->recordLockVersion = $this->recordLockVersionOf($this->getRecord());

        if (store($this)->has('redirect')) {
            $this->releaseRecordLock();
        }
    }

    /**
     * Inline saves of a single field go past `save()`, so they pass the same check.
     */
    public function saveFormComponentOnly(Component $component): void
    {
        if (!$this->recordLockPermitsSave()) {
            return;
        }

        parent::saveFormComponentOnly($component);

        $this->recordLockVersion = $this->recordLockVersionOf($this->getRecord());
    }
}
