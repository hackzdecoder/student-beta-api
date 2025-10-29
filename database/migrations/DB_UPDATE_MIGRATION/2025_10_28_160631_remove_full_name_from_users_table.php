<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('school_manager_connect')->table('users', function (Blueprint $table) {
            // Remove full_name column if it exists
            if (Schema::connection('school_manager_connect')->hasColumn('users', 'full_name')) {
                $table->dropColumn('full_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('school_manager_connect')->table('users', function (Blueprint $table) {
            // Add back full_name column if rolling back
            if (!Schema::connection('school_manager_connect')->hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('user_id');
            }
        });
    }
};