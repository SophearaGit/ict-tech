<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ON/OFF switch (every existing brand starts ON, so nothing disappears from the shop) and the
     * optional logo (just the file name; the file lives in public/uploads/brands). Nothing is rewritten.
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->string('logo', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'logo']);
        });
    }
};
