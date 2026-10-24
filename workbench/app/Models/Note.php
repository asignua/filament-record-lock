<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with a UUID key, edited in a modal.
 *
 * @property string $id
 * @property string $title
 */
class Note extends Model
{
    use HasLocks;
    use HasUuids;

    protected $guarded = [];
}
