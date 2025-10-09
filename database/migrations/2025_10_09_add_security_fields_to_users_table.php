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
        Schema::table('users', function (Blueprint $table) {
            // Account status
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('is_admin')->default(false)->after('is_active');
            $table->timestamp('blocked_at')->nullable()->after('is_admin');
            $table->string('block_reason')->nullable()->after('blocked_at');
            $table->timestamp('locked_at')->nullable()->after('block_reason');
            $table->string('lock_reason')->nullable()->after('locked_at');

            // Phone number (optional)
            $table->string('phone')->nullable()->unique()->after('email');

            // API authentication
            $table->string('api_secret')->nullable();
            $table->timestamp('api_secret_rotated_at')->nullable();

            // Security tracking
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('last_failed_login_at')->nullable();
            $table->timestamp('last_successful_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->text('last_login_user_agent')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->json('password_history')->nullable(); // Store hashed passwords

            // Compliance
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_version')->nullable();

            // Indexes for performance
            $table->index('is_active');
            $table->index('blocked_at');
            $table->index('locked_at');
            $table->index('last_successful_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['is_active']);
            $table->dropIndex(['blocked_at']);
            $table->dropIndex(['locked_at']);
            $table->dropIndex(['last_successful_login_at']);

            // Drop columns
            $table->dropColumn([
                'is_active',
                'is_admin',
                'blocked_at',
                'block_reason',
                'locked_at',
                'lock_reason',
                'phone',
                'api_secret',
                'api_secret_rotated_at',
                'failed_login_attempts',
                'last_failed_login_at',
                'last_successful_login_at',
                'last_login_ip',
                'last_login_user_agent',
                'password_changed_at',
                'password_history',
                'terms_accepted_at',
                'terms_version',
                'privacy_accepted_at',
                'privacy_version',
            ]);
        });
    }
};