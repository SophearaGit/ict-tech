<?php

namespace App\Http\Controllers\Admin\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The name rule shared by the Brands and Categories pages. Uniqueness ignores case (and the row being
 * renamed) and is checked with lower(name) so it behaves the same on MySQL and on the SQLite test
 * database. Rename rules apply to the NEW name only: a legacy name copied unchanged is never re-validated.
 */
trait ValidatesUniqueNames
{
    /**
     * @param  class-string<Model>  $model  the model whose `name` column must stay unique
     * @param  string  $takenMessage  what the admin reads when the name is already used
     */
    private function uniqueNameRules(string $model, ?Model $ignoring, string $takenMessage): array
    {
        return [
            'bail',
            'required',
            'string',
            'max:50',
            'regex:/^[\p{L}\p{M}\p{N} .\-]+$/u', // \p{M}: combining marks, which Khmer and other scripts need inside a word
            function (string $attribute, mixed $value, Closure $fail) use ($model, $ignoring, $takenMessage) {
                $taken = $model::whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                    ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
                    ->exists();

                if ($taken) {
                    $fail($takenMessage);
                }
            },
        ];
    }
}
