<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Resources\Pages\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * For a page that edits records in a modal (`ManageRecords` with a table `EditAction`): the record is locked
 * while its modal is open. Name taken from kenepa/resource-lock.
 *
 *     class ManageArticles extends ManageRecords
 *     {
 *         use UsesSimpleResourceLock;
 *     }
 *
 * Which actions lock is decided by {@see self::recordLockActionNames()} (default: `edit`).
 */
// @phpstan-ignore-next-line trait.unused (used by the host's pages; the tests exercise it)
trait UsesSimpleResourceLock
{
    use UsesLocks;

    /**
     * @return list<string>
     */
    protected function recordLockActionNames(): array
    {
        return ['edit'];
    }

    protected function recordLockMountedAction(): ?\Filament\Actions\Action
    {
        $action = $this->getMountedAction(0);

        return $action !== null && in_array($action->getName(), $this->recordLockActionNames(), true) ? $action : null;
    }

    protected function recordLockRecord(): ?Model
    {
        $record = $this->recordLockMountedAction()?->getRecord();

        return $record instanceof Model ? $record : null;
    }

    protected function recordLockRefresh(): void
    {
        $mounted = $this->mountedActions[0] ?? null;

        if ($mounted === null) {
            return;
        }

        // The modal's form was filled with the old data: close and open it again so it is filled from the database.
        $this->unmountAction();
        $this->mountAction($mounted['name'], $mounted['arguments'] ?? [], $mounted['context'] ?? []);
    }

    public function recordLockReturnUrl(): ?string
    {
        return null;
    }

    public function recordLockDismiss(): void
    {
        $this->unmountAction();
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $result = parent::mountAction($name, $arguments, $context);

        if (count($this->mountedActions ?? []) === 1 && $this->recordLockMountedAction() !== null && !$this->recordLockActive) {
            $this->initializeRecordLock();
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function callMountedAction(array $arguments = []): mixed
    {
        if (count($this->mountedActions ?? []) === 1 && $this->recordLockActive && !$this->recordLockPermitsSave()) {
            return null;
        }

        return parent::callMountedAction($arguments);
    }

    public function unmountAction(bool|string|null $cancelParentActions = null): void
    {
        if (count($this->mountedActions ?? []) === 1 && $this->recordLockActive && $this->recordLockMountedAction() !== null) {
            $this->finishRecordLock();
        }

        parent::unmountAction($cancelParentActions);
    }
}
