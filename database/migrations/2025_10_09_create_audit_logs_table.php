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
        // Audit logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->text('request_url')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->json('geolocation')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->index();

            // Composite indexes for efficient querying
            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        // Security incidents table
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->index();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index();
            $table->json('data');
            $table->enum('status', ['open', 'investigating', 'resolved', 'false_positive'])
                ->default('open')
                ->index();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['severity', 'status']);
        });

        // Failed login attempts table for tracking
        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->integer('attempt_count')->default(1);
            $table->timestamp('last_attempt_at');
            $table->timestamp('blocked_until')->nullable();
            $table->timestamps();

            $table->index(['email', 'ip_address']);
            $table->index(['ip_address', 'created_at']);
        });

        // API rate limits tracking
        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Rate limit key
            $table->integer('hits')->default(0);
            $table->timestamp('reset_at');
            $table->timestamps();

            $table->index(['key', 'reset_at']);
        });

        // User activity sessions for security monitoring
        Schema::create('user_activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id')->unique();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->string('device_type', 50)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('platform', 50)->nullable();
            $table->json('location')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_activity_at']);
            $table->index(['session_id', 'last_activity_at']);
        });

        // Transaction audit trail
        Schema::create('transaction_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50); // created, approved, rejected, cancelled, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['transaction_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_audit_trail');
        Schema::dropIfExists('user_activity_sessions');
        Schema::dropIfExists('api_rate_limits');
        Schema::dropIfExists('failed_login_attempts');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('audit_logs');
    }
};