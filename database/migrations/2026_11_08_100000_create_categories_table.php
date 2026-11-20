<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop's categories, until now a list in config/shop.php. Products keep pointing at a category
     * by its code (`products.category` = `categories.slug`), so no product is rewritten. The four
     * current categories are copied in, all ON, in their current order (the id is the display order).
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 50)->unique();
            $table->string('icon', 16)->default('📦');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['phones', 'Phones', '📱'],
            ['earphones', 'Earphones', '🎧'],
            ['powerbanks', 'Power Banks', '🔋'],
            ['laptops', 'Laptops', '💻'],
        ] as [$slug, $name, $icon]) {
            DB::table('categories')->insert([
                'slug' => $slug,
                'name' => $name,
                'icon' => $icon,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
