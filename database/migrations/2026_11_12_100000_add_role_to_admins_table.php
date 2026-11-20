<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // New rows start as staff: an admin created without naming a role never gets full access by accident.
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role', 10)->default('staff')->after('password');
        });

        // Everyone who is an admin today keeps full access: they become the owners.
        // Only `role` is written; emails and password hashes are never read or changed.
        DB::table('admins')->update(['role' => 'owner']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
