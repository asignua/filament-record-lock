<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with a ULID key (tests only).
 *
 * @property string $id
 * @property string $title
 */
class Ticket extends Model
{
    use HasLocks;
    use HasUlids;

    protected $guarded = [];
}
