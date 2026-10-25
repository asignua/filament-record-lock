<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Support;

use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * `lockable_id` is a string column (it must hold ints, UUIDs and ULIDs). Eager loading would send the parents'
 * integer keys through `whereIntegerInRaw`, which PostgreSQL rejects (`varchar = integer`) and MySQL compares by
 * silent conversion. Binding the keys as strings is correct everywhere.
 *
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends MorphOne<TRelatedModel, TDeclaringModel>
 */
class StringKeyMorphOne extends MorphOne
{
    /**
     * @param array<int, \Illuminate\Database\Eloquent\Model> $models
     */
    public function addEagerConstraints(array $models): void
    {
        // @phpstan-ignore argument.type
        $parentKeys = $this->getKeys($models, $this->localKey);
        $keys = array_values(array_unique(array_map('strval', $parentKeys)));

        $this->getRelationQuery()
            ->whereIn($this->foreignKey, $keys)
            ->where($this->morphType, $this->morphClass);
    }
}
