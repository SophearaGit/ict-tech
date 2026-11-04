<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Step 3 of 3: every product must have a brand, and the old text column goes away. */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
        });

        DB::statement('update products set brand = (select name from brands where brands.id = products.brand_id)');

        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable(false)->change();
            $table->unsignedBigInteger('brand_id')->nullable()->change();
        });
    }
};
