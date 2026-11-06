<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** brands.name is string(50). */
    private const MAX_NAME_LENGTH = 50;

    /**
     * Step 2 of 3: turn the free-text products.brand values into brand rows.
     *
     * Brands that differ only in case or surrounding spaces become one brand. Its name is the
     * spelling used by the most products; on a tie, the spelling of the lowest product id.
     * Names are copied as they are (no character check), so a legacy name stays untouched.
     *
     * Safe to fail: a name that cannot fit in brands.name stops the migration BEFORE anything is
     * written, and every write happens in one transaction, so a failure never leaves some
     * products linked and others not.
     */
    public function up(): void
    {
        $groups = $this->legacyBrandGroups();

        foreach ($groups as $group) {
            if (mb_strlen($group['name']) > self::MAX_NAME_LENGTH) {
                throw new RuntimeException(sprintf(
                    'Brand name "%s" is longer than %d characters, so it cannot be moved into the brands table. '
                    .'Shorten it in products.brand, then run the migration again.',
                    $group['name'],
                    self::MAX_NAME_LENGTH,
                ));
            }
        }

        DB::transaction(function () use ($groups) {
            foreach ($groups as $group) {
                // Ask the database whether the brand already exists: its unique index also ignores
                // accents on MySQL (Café = Cafe), which grouping by PHP lowercase cannot see.
                $brandId = DB::table('brands')->where('name', $group['name'])->value('id')
                    ?? DB::table('brands')->insertGetId([
                        'name' => $group['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach (array_chunk($group['ids'], 500) as $ids) {
                    DB::table('products')->whereIn('id', $ids)->update(['brand_id' => $brandId]);
                }
            }
        });
    }

    /** Nothing to undo here: the next migration's down() refills products.brand from brands. */
    public function down(): void
    {
    }

    /** @return list<array{name: string, ids: list<int>}> one entry per brand, in order of first product id */
    private function legacyBrandGroups(): array
    {
        $groups = [];

        foreach (DB::table('products')->orderBy('id')->get(['id', 'brand']) as $row) {
            $spelling = trim((string) $row->brand);
            $key = mb_strtolower($spelling);

            $groups[$key]['ids'][] = $row->id;
            $groups[$key]['spellings'][$spelling]['count'] = ($groups[$key]['spellings'][$spelling]['count'] ?? 0) + 1;
            $groups[$key]['spellings'][$spelling]['first_id'] ??= $row->id;
        }

        return array_values(array_map(fn (array $group) => [
            'name' => $this->winningSpelling($group['spellings']),
            'ids' => $group['ids'],
        ], $groups));
    }

    /** @param array<string, array{count: int, first_id: int}> $spellings */
    private function winningSpelling(array $spellings): string
    {
        uksort($spellings, fn (string $a, string $b) => [$spellings[$b]['count'], $spellings[$a]['first_id']]
            <=> [$spellings[$a]['count'], $spellings[$b]['first_id']]);

        return (string) array_key_first($spellings);
    }
};
