<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Step 2 of 3: turn the free-text products.brand values into brand rows.
     *
     * Brands that differ only in case or surrounding spaces become one brand. Its name is the
     * spelling used by the most products; on a tie, the spelling of the lowest product id.
     * Names are copied as they are (no character check), so a legacy name stays untouched.
     */
    public function up(): void
    {
        $groups = [];

        foreach (DB::table('products')->orderBy('id')->get(['id', 'brand']) as $row) {
            $spelling = trim((string) $row->brand);
            $key = mb_strtolower($spelling);

            $groups[$key]['ids'][] = $row->id;
            $groups[$key]['spellings'][$spelling]['count'] = ($groups[$key]['spellings'][$spelling]['count'] ?? 0) + 1;
            $groups[$key]['spellings'][$spelling]['first_id'] ??= $row->id;
        }

        foreach ($groups as $group) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => $this->winningSpelling($group['spellings']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_chunk($group['ids'], 500) as $ids) {
                DB::table('products')->whereIn('id', $ids)->update(['brand_id' => $brandId]);
            }
        }
    }

    /** Nothing to undo here: the next migration's down() refills products.brand from brands. */
    public function down(): void
    {
    }

    /** @param array<string, array{count: int, first_id: int}> $spellings */
    private function winningSpelling(array $spellings): string
    {
        uksort($spellings, fn (string $a, string $b) => [$spellings[$b]['count'], $spellings[$a]['first_id']]
            <=> [$spellings[$a]['count'], $spellings[$b]['first_id']]);

        return (string) array_key_first($spellings);
    }
};
