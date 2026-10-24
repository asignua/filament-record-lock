<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Resources\RecordLockResource\Pages;

use Asignua\FilamentRecordLock\Resources\RecordLockResource;
use Filament\Resources\Pages\ListRecords;

class ListRecordLocks extends ListRecords
{
    protected static string $resource = RecordLockResource::class;
}
