<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'brand', 'type', 'category', 'price', 'qty', 'description', 'specs', 'image', 'image_alt', 'trending', 'badge'])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'qty' => 'integer',
            'specs' => 'array',
            'trending' => 'boolean',
        ];
    }
}
