<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Events\TransactionProcessed;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        // Get transactions where user is either sender or receiver
        // Using union for better performance with large datasets
        $sentTransactions = Transaction::query()->where('sender_id', $user->id)
            ->select('*', DB::raw("'debit' as transaction_type"));

        $receivedTransactions = Transaction::query()->where('receiver_id', $user->id)
            ->select('*', DB::raw("'credit' as transaction_type"));

        $transactions = $sentTransactions->union($receivedTransactions)
            ->orderBy('created_at', 'desc')
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->paginate($perPage);

        // Transform transactions for better frontend consumption
        $transactions->getCollection()->transform(function ($transaction) use ($user) {
            $isSender = $transaction->sender_id === $user->id;

            return [
                'id' => $transaction->id,
                'reference_number' => $transaction->reference_number,
                'type' => $isSender ? 'debit' : 'credit',
                'amount' => $transaction->amount,
                'commission_fee' => $isSender ? $transaction->commission_fee : 0,
                'total' => $isSender ? $transaction->total_deducted : $transaction->amount,
                'sender' => [
                    'id' => $transaction->sender->id,
                    'name' => $transaction->sender->name,
                    'email' => $transaction->sender->email,
                ],
                'receiver' => [
                    'id' => $transaction->receiver->id,
                    'name' => $transaction->receiver->name,
                    'email' => $transaction->receiver->email,
                ],
                'status' => $transaction->status,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
                'processed_at' => $transaction->processed_at,
            ];
        });

        return response()->json([
            'transactions' => $transactions,
            'balance' => $user->balance,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    public function storeTransaction(TransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // 5 transfers per minute per user
        $key = 'transfer:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Too many transfer attempts. Please try again in {$seconds} seconds.",
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 seconds window

        try {
            // Use the transaction service for better concurrency handling
            $transaction = $this->transactionService->processTransfer(
                $user->id,
                $validated['receiver_id'],
                $validated['amount'],
                $validated['description'] ?? null
            );

            // Transaction is already logged in the service

            return response()->json([
                'message' => 'Transfer successful',
                'transaction' => [
                    'id' => $transaction->id,
                    'reference_number' => $transaction->reference_number,
                    'amount' => $transaction->amount,
                    'commission_fee' => $transaction->commission_fee,
                    'total_deducted' => $transaction->total_deducted,
                    'sender' => [
                        'id' => $transaction->sender->id,
                        'name' => $transaction->sender->name,
                    ],
                    'receiver' => [
                        'id' => $transaction->receiver->id,
                        'name' => $transaction->receiver->name,
                    ],
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                ],
                'new_balance' => $request->user()->fresh()->balance
            ], 201);

        } catch (\Exception $e) {
            // Clear rate limit on failure
            RateLimiter::clear($key);

            // Log is already handled in the service
            return response()->json([
                'message' => 'Transaction failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
