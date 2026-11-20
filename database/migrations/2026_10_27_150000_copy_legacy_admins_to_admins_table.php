<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admins used to be rows in `users` flagged `is_admin`. Copy them into
     * `admins`, keeping the stored password hash untouched so existing
     * passwords keep working. Re-running is safe: existing emails are skipped.
     */
    public function up(): void
    {
        foreach (DB::table('users')->where('is_admin', true)->orderBy('id')->get() as $user) {
            DB::table('admins')->insertOrIgnore([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op: the copied rows may have been edited since, and
     * the original `users` rows were never touched.
     */
    public function down(): void
    {
        //
    }
};
