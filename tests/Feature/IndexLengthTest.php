<?php

declare(strict_types=1);

namespace Asignua\FilamentRecordLock\Tests\Feature;

use Asignua\FilamentRecordLock\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite does not enforce MySQL's index key limit (3072 bytes on InnoDB with DYNAMIC rows, 4 bytes per utf8mb4
 * character), so a composite index over long varchar columns passes here and fails at `migrate` on production.
 * This sums the declared lengths of every index of the table.
 */
class IndexLengthTest extends TestCase
{
    private const int MAX_INDEX_BYTES = 3072;

    public function test_every_index_fits_the_mysql_key_length_limit(): void
    {
        $table = (string) config('filament-record-lock.table');
        $columns = collect(Schema::getColumns($table))->keyBy('name');
        $indexes = Schema::getIndexes($table);

        $this->assertNotEmpty($indexes);

        foreach ($indexes as $index) {
            $bytes = 0;

            foreach ($index['columns'] as $name) {
                $type = (string) ($columns[$name]['type'] ?? '');
                $bytes += $this->bytesOf($type);
            }

            $this->assertLessThanOrEqual(
                self::MAX_INDEX_BYTES,
                $bytes,
                "Index [{$index['name']}] on [".implode(', ', $index['columns'])."] is {$bytes} bytes.",
            );
        }
    }

    public function test_the_morph_pair_is_unique(): void
    {
        $unique = collect(Schema::getIndexes((string) config('filament-record-lock.table')))
            ->first(fn (array $index): bool => $index['unique'] && $index['columns'] === ['lockable_type', 'lockable_id']);

        $this->assertNotNull($unique, 'At most one lock per record is what makes acquiring atomic.');
    }

    /**
     * varchar(255) -> 1020 bytes; an unsized varchar is MySQL's 255 default; other types are small.
     */
    private function bytesOf(string $type): int
    {
        if (preg_match('/^(?:var)?char\((\d+)\)/i', $type, $m)) {
            return (int) $m[1] * 4;
        }

        if (preg_match('/^(?:var)?char$/i', $type)) {
            return 255 * 4;
        }

        return 8;
    }
}
