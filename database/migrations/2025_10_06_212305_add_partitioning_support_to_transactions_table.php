<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add database-level optimizations for handling millions of records.
     */
    public function up(): void
    {
        // Add a year_month column for efficient partitioning/archiving
        // Note: We'll populate this via model events instead of virtual column
        if (!Schema::hasColumn('transactions', 'year_month')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('year_month', 7)->nullable()->index();
            });
        }

        // Create a summary table for fast balance lookups
        // This avoids calculating from millions of transaction records
        Schema::create('user_balance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->decimal('total_sent', 15, 2)->default(0);
            $table->decimal('total_received', 15, 2)->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('cached_balance', 15, 2);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('last_transaction_at');
        });

        // Create archived transactions table for old records
        Schema::create('archived_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('original_id');
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_fee', 10, 2);
            $table->decimal('total_deducted', 10, 2);
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'completed', 'failed']);
            $table->string('reference_number');
            $table->text('description')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->useCurrent();

            // Indexes for archived data
            $table->index('original_id');
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
            $table->index('reference_number');
            $table->index('archived_at');
        });

        // Create a materialized view for transaction statistics (if using MySQL 8.0+)
        if (DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION) >= '8.0') {
            DB::statement("
                CREATE OR REPLACE VIEW transaction_monthly_stats AS
                SELECT
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total_transactions,
                    SUM(amount) as total_volume,
                    SUM(commission_fee) as total_commission,
                    AVG(amount) as avg_transaction_amount
                FROM transactions
                WHERE status = 'completed'
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the view if it exists
        DB::statement("DROP VIEW IF EXISTS transaction_monthly_stats");

        // Drop tables
        Schema::dropIfExists('archived_transactions');
        Schema::dropIfExists('user_balance_summaries');

        // Remove the virtual column
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('year_month');
        });
    }
};