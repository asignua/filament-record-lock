<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Notes\Pages;

use Asignua\FilamentRecordLock\Resources\Pages\Concerns\UsesSimpleResourceLock;
use Filament\Resources\Pages\ManageRecords;
use Workbench\App\Filament\Resources\Notes\NoteResource;

class ManageNotes extends ManageRecords
{
    use UsesSimpleResourceLock;

    protected static string $resource = NoteResource::class;
}
