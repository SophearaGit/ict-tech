<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A shop category. `slug` is the short code products point at (`products.category`); it is written once
 * and never changes, so a rename never breaks an address or a product. `is_active` is the ON/OFF switch,
 * written by the admin controller with forceFill, never mass-assigned from a request. There is no
 * products() relation: products join by `products.category = categories.slug`, and the controller counts them.
 */
#[Fillable(['slug', 'name', 'icon'])]
class Category extends Model
{
    /** Categories that are switched ON. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The code for a new category, made from its name: `Str::slug` cut to 50 characters; when that is
     * empty (a name with no Latin letters) `c` plus 8 random hex characters; when the code is taken,
     * `-2`, `-3`... is added (the base is shortened so the result never passes 50 characters).
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = mb_substr(Str::slug($name), 0, 50);
        if ($base === '') {
            $base = 'c'.bin2hex(random_bytes(4));
        }

        $slug = $base;
        for ($n = 2; static::where('slug', $slug)->exists(); $n++) {
            $suffix = '-'.$n;
            $slug = mb_substr($base, 0, 50 - strlen($suffix)).$suffix;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
