<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TransactionRepository
{
    public function getUserTransactionsOptimized(int $userId, int $perPage = 20, ?string $cursor = null): array
    {
        // Use cursor-based pagination for millions of records
        $query = Transaction::query()
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->orderBy('id', 'desc');

        if ($cursor) {
            $transactions = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
        } else {
            $transactions = $query->cursorPaginate($perPage);
        }

        return [
            'data' => $this->transformTransactions($transactions->items(), $userId),
            'next_cursor' => $transactions->nextCursor()?->encode(),
            'prev_cursor' => $transactions->previousCursor()?->encode(),
            'has_more' => $transactions->hasMorePages(),
        ];
    }

    public function getTransactionsByDateRange(int $userId, string $startDate, string $endDate, int $limit = 100): Collection
    {
        $cacheKey = "user_transactions_{$userId}_{$startDate}_{$endDate}_{$limit}";

        // Get transactions using database partition hints for date ranges.
        // This avoids calculating from millions of transaction records.
        return Cache::remember($cacheKey, 300, function () use ($userId, $startDate, $endDate, $limit) {
            return Transaction::query()
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    public function getUserSummary(int $userId): array
    {
        $summary = DB::table('user_balance_summaries')
            ->where('user_id', $userId)
            ->first();

        if (!$summary) {
            // If summary doesn't exist, calculate and cache it
            return $this->calculateAndCacheSummary($userId);
        }

        return [
            'total_sent' => $summary->total_sent,
            'total_received' => $summary->total_received,
            'total_commission' => $summary->total_commission,
            'transaction_count' => $summary->transaction_count,
            'last_transaction_at' => $summary->last_transaction_at,
        ];
    }

    private function calculateAndCacheSummary(int $userId): array
    {
        // Calculate summary
        $stats = DB::table('transactions')
            ->selectRaw("
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as total_sent,
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as total_received,
                SUM(CASE WHEN sender_id = ? THEN commission_fee ELSE 0 END) as total_commission,
                COUNT(*) as transaction_count,
                MAX(created_at) as last_transaction_at
            ", [$userId, $userId, $userId])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where('status', 'completed')
            ->first();

        $user = User::query()->find($userId);

        // Store in the summary table
        DB::table('user_balance_summaries')->updateOrInsert(
            ['user_id' => $userId],
            [
                'total_sent' => $stats->total_sent ?? 0,
                'total_received' => $stats->total_received ?? 0,
                'total_commission' => $stats->total_commission ?? 0,
                'transaction_count' => $stats->transaction_count ?? 0,
                'cached_balance' => $user->balance,
                'last_transaction_at' => $stats->last_transaction_at,
                'updated_at' => now(),
            ]
        );

        return [
            'total_sent' => $stats->total_sent ?? 0,
            'total_received' => $stats->total_received ?? 0,
            'total_commission' => $stats->total_commission ?? 0,
            'transaction_count' => $stats->transaction_count ?? 0,
            'last_transaction_at' => $stats->last_transaction_at,
        ];
    }

    public function updateUserSummary(int $userId, Transaction $transaction, bool $isSender): void
    {
        $updates = [
            'transaction_count' => DB::raw('transaction_count + 1'),
            'last_transaction_at' => $transaction->created_at,
            'updated_at' => now(),
        ];

        if ($isSender) {
            $updates['total_sent'] = DB::raw("total_sent + {$transaction->amount}");
            $updates['total_commission'] = DB::raw("total_commission + {$transaction->commission_fee}");
        } else {
            $updates['total_received'] = DB::raw("total_received + {$transaction->amount}");
        }

        DB::table('user_balance_summaries')
            ->where('user_id', $userId)
            ->update($updates);
    }

    public function archiveOldTransactions(int $daysOld = 365): int
    {
        $cutoffDate = now()->subDays($daysOld);

        // Get transactions to archive in chunks
        $archived = 0;
        Transaction::query()->where('created_at', '<', $cutoffDate)
            ->where('status', 'completed')
            ->chunk(1000, function ($transactions) use (&$archived) {
                $archiveData = $transactions->map(function ($t) {
                    return [
                        'original_id' => $t->id,
                        'sender_id' => $t->sender_id,
                        'receiver_id' => $t->receiver_id,
                        'amount' => $t->amount,
                        'commission_fee' => $t->commission_fee,
                        'total_deducted' => $t->total_deducted,
                        'type' => $t->type,
                        'status' => $t->status,
                        'reference_number' => $t->reference_number,
                        'description' => $t->description,
                        'processed_at' => $t->processed_at,
                        'created_at' => $t->created_at,
                        'updated_at' => $t->updated_at,
                        'archived_at' => now(),
                    ];
                })->toArray();

                // Bulk insert to archive table
                DB::table('archived_transactions')->insert($archiveData);

                // Delete from main table
                Transaction::query()->whereIn('id', $transactions->pluck('id'))->delete();

                $archived += count($archiveData);
            });

        return $archived;
    }

    public function searchTransactions(int $userId, string $search, int $limit = 50): Collection
    {
        return Transaction::query()
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', $search . '%')
                  ->orWhere('amount', $search)
                  ->orWhereHas('sender', function ($q) use ($search) {
                      $q->where('name', 'LIKE', '%' . $search . '%');
                  })
                  ->orWhereHas('receiver', function ($q) use ($search) {
                      $q->where('name', 'LIKE', '%' . $search . '%');
                  });
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getMonthlyStats(int $months = 12): Collection
    {
        return DB::table('transaction_monthly_stats')
            ->orderBy('month', 'desc')
            ->limit($months)
            ->get();
    }

    private function transformTransactions($transactions, int $userId): array
    {
        return collect($transactions)->map(function ($transaction) use ($userId) {
            $isSender = $transaction->sender_id === $userId;

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
        })->toArray();
    }
}
