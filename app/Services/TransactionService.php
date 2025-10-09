<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Events\TransactionProcessed;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TransactionService
{
    protected TransactionRepository $repository;

    public function __construct(TransactionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function processTransfer(int $senderId, int $receiverId, float $amount, ?string $description = null): Transaction
    {
        // Validate basic business rules
        if ($senderId === $receiverId) {
            throw new \Exception('Cannot transfer money to yourself');
        }

        if ($amount <= 0) {
            throw new \Exception('Amount must be greater than zero');
        }

        // Calculate fees
        $commissionFee = Transaction::calculateCommission($amount);
        $totalDeduction = Transaction::calculateTotalDeduction($amount);

        // Use ordered locking to prevent deadlocks
        // Always lock users in the same order (lower ID first)
        $lockOrder = $senderId < $receiverId
            ? [$senderId, $receiverId]
            : [$receiverId, $senderId];

        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return DB::transaction(function () use ($senderId, $receiverId, $amount, $commissionFee, $totalDeduction, $description, $lockOrder) {
                    // Lock users in consistent order to prevent deadlocks
                    $users = User::query()->whereIn('id', $lockOrder)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    // Get sender and receiver from locked collection
                    $sender = $users->get($senderId);
                    $receiver = $users->get($receiverId);

                    if (!$sender) {
                        throw new \Exception('Sender not found');
                    }

                    if (!$receiver) {
                        throw new \Exception('Receiver not found');
                    }

                    // Check balance with double precision to avoid floating point issues
                    $senderBalance = (float) $sender->balance;
                    // Using regular comparison instead of bccomp for now
                    if ($senderBalance < $totalDeduction) {
                        throw new \Exception(sprintf(
                            'Insufficient balance. Required: $%.2f (including commission), Available: $%.2f',
                            $totalDeduction,
                            $senderBalance
                        ));
                    }

                    // Create transaction record first (for audit trail)
                    $transaction = Transaction::create([
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'amount' => $amount,
                        'commission_fee' => $commissionFee,
                        'total_deducted' => $totalDeduction,
                        'type' => 'debit',
                        'status' => 'pending',
                        'reference_number' => Transaction::generateReferenceNumber(),
                        'description' => $description,
                    ]);

                    // Update balances using increment/decrement for atomic operations
                    // This provides an additional layer of safety
                    User::query()->where('id', $senderId)
                        ->decrement('balance', $totalDeduction);

                    User::query()->where('id', $receiverId)
                        ->increment('balance', $amount);

                    // Mark transaction as completed
                    $transaction->update([
                        'status' => 'completed',
                        'processed_at' => now(),
                    ]);

                    // Refresh models to get updated balances
                    $sender->refresh();
                    $receiver->refresh();

                    // Load relationships for response
                    $transaction->load(['sender:id,name,email', 'receiver:id,name,email']);

                    // Broadcast event (consider queueing for better performance)
                    broadcast(new TransactionProcessed($transaction, $sender->balance, $receiver->balance));

                    // Update user summaries for performance optimization
                    $this->repository->updateUserSummary($senderId, $transaction, true);
                    $this->repository->updateUserSummary($receiverId, $transaction, false);

                    // Clear any cached balance data
                    Cache::forget("user_balance_{$senderId}");
                    Cache::forget("user_balance_{$receiverId}");

                    // Log successful transaction
                    Log::info('Transaction completed', [
                        'reference' => $transaction->reference_number,
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'amount' => $amount,
                        'commission' => $commissionFee,
                    ]);

                    return $transaction;
                }, 5); // 5 seconds timeout for transaction

            } catch (\Illuminate\Database\DeadlockException $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    Log::error('Transaction failed after max retries due to deadlock', [
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'amount' => $amount,
                        'attempt' => $attempt,
                    ]);
                    throw new \Exception('Transaction failed due to system congestion. Please try again.');
                }

                // Exponential backoff
                usleep(100000 * pow(2, $attempt)); // 100ms, 200ms, 400ms
                continue;
            }
        }

        throw new \Exception('Transaction could not be completed');
    }

    public function getCachedBalance(int $userId): float
    {
        return Cache::remember("user_balance_{$userId}", 10, function () use ($userId) {
            $user = User::query()->find($userId);
            return $user ? (float) $user->balance : 0.0;
        });
    }

    public function invalidateBalanceCache(int $userId): void
    {
        Cache::forget("user_balance_{$userId}");
    }

    public function getUserTransactions(int $userId, int $perPage = 20, ?string $cursor = null): array
    {
        return $this->repository->getUserTransactionsOptimized($userId, $perPage, $cursor);
    }

    public function getUserSummary(int $userId): array
    {
        return $this->repository->getUserSummary($userId);
    }
}
