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
        Schema::table('transactions', function (Blueprint $table) {
            // Composite index for efficient querying of user transactions with date filtering
            // This is critical for handling millions of records
            $table->index(['sender_id', 'status', 'created_at'], 'idx_sender_status_created');
            $table->index(['receiver_id', 'status', 'created_at'], 'idx_receiver_status_created');

            // Index for efficient balance calculations if we ever need to recalculate from transactions
            $table->index(['sender_id', 'status', 'processed_at'], 'idx_sender_status_processed');
            $table->index(['receiver_id', 'status', 'processed_at'], 'idx_receiver_status_processed');

            // Index for monitoring and reporting
            $table->index(['status', 'processed_at'], 'idx_status_processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_sender_status_created');
            $table->dropIndex('idx_receiver_status_created');
            $table->dropIndex('idx_sender_status_processed');
            $table->dropIndex('idx_receiver_status_processed');
            $table->dropIndex('idx_status_processed');
        });
    }
};