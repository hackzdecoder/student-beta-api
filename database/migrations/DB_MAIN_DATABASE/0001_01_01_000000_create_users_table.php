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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('full_name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};


// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     /**
//      * Run the migrations.
//      */
//     public function up(): void
//     {
//         // Use the specific connection for main_db
//         Schema::connection('main_database')->table('users', function (Blueprint $table) {
//             // Safely add new columns only if they don't exist yet
//             if (!Schema::connection('main_database')->hasColumn('users', 'user_id')) {
//                 $table->string('user_id')->nullable()->after('id');
//             }
//             if (!Schema::connection('main_database')->hasColumn('users', 'full_name')) {
//                 $table->string('full_name')->nullable()->after('user_id');
//             }
//             if (!Schema::connection('main_database')->hasColumn('users', 'username')) {
//                 $table->string('username')->nullable()->after('full_name');
//             }
//             if (!Schema::connection('main_database')->hasColumn('users', 'otp_code')) {
//                 $table->string('otp_code')->nullable()->after('password');
//             }
//             if (!Schema::connection('main_database')->hasColumn('users', 'otp_verified_at')) {
//                 $table->string('otp_verified_at')->nullable()->after('otp_code');
//             }
//         });

//         // These are kept intact; no changes unless you want to modify them
//         Schema::connection('main_database')->create('password_reset_tokens', function (Blueprint $table) {
//             if (!Schema::connection('main_database')->hasTable('password_reset_tokens')) {
//                 $table->string('email')->primary();
//                 $table->string('token');
//                 $table->timestamp('created_at')->nullable();
//             }
//         });

//         Schema::connection('main_database')->create('sessions', function (Blueprint $table) {
//             if (!Schema::connection('main_database')->hasTable('sessions')) {
//                 $table->string('id')->primary();
//                 $table->foreignId('user_id')->nullable()->index();
//                 $table->string('ip_address', 45)->nullable();
//                 $table->text('user_agent')->nullable();
//                 $table->longText('payload');
//                 $table->integer('last_activity')->index();
//             }
//         });
//     }

//     /**
//      * Reverse the migrations.
//      */
//     public function down(): void
//     {
//         Schema::connection('main_database')->table('users', function (Blueprint $table) {
//             $columns = ['user_id', 'full_name', 'username', 'otp_code', 'otp_verified_at'];
//             foreach ($columns as $col) {
//                 if (Schema::connection('main_database')->hasColumn('users', $col)) {
//                     $table->dropColumn($col);
//                 }
//             }
//         });

//         // Optional cleanup for other tables
//         Schema::connection('main_database')->dropIfExists('password_reset_tokens');
//         Schema::connection('main_database')->dropIfExists('sessions');
//     }
// };
