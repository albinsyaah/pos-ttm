<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - `is_active` lets a Super Admin revoke a user's access without deleting
     *   the account (they're logged out / blocked from logging in again).
     * - The legacy `role` string column is left as-is (still required). Real
     *   authorization now lives in the spatie/laravel-permission tables
     *   (roles/permissions); this column is kept in sync automatically for
     *   backward compatibility / quick display only.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
