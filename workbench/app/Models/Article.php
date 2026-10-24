<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Asignua\FilamentRecordLock\Models\Concerns\HasLocks;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 */
class Article extends Model
{
    use HasLocks;

    protected $guarded = [];
}
