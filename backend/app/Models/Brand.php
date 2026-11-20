<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `is_active` is the ON/OFF switch and `logo` the stored logo file name. Both are written by the
 * admin controller with forceFill, never mass-assigned from a request, so only `name` is fillable.
 */
#[Fillable(['name'])]
class Brand extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Brands that are switched ON. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The public address of the logo, or null when the brand has none. */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo
            ? asset(config('shop.brand_logos.path').'/'.$this->logo)
            : null);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
