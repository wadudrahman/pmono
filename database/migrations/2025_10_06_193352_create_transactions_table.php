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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->constrained('users');
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_fee', 10, 2);
            $table->decimal('total_deducted', 10, 2); // amount + commission_fee
            $table->enum('type', ['credit', 'debit']); // from the user view
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('reference_number')->unique(); // transaction reference
            $table->text('description')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['sender_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
            $table->index('reference_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
