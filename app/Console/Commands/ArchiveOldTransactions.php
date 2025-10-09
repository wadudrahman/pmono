<?php

namespace App\Console\Commands;

use App\Repositories\TransactionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ArchiveOldTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:archive {--days=365 : Number of days old to archive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive old transactions to maintain performance with millions of records';

    protected TransactionRepository $repository;

    public function __construct(TransactionRepository $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int)$this->option('days');

        $this->info("Starting archive process for transactions older than {$days} days...");

        try {
            $startTime = microtime(true);

            $archived = $this->repository->archiveOldTransactions($days);

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("Successfully archived {$archived} transactions in {$duration} seconds.");

            // Log the operation
            Log::info('Transaction archive completed', [
                'archived_count' => $archived,
                'days_old' => $days,
                'duration_seconds' => $duration
            ]);

            return CommandAlias::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Archive process failed: " . $e->getMessage());

            Log::error('Transaction archive failed', [
                'error' => $e->getMessage(),
                'days_old' => $days
            ]);

            return CommandAlias::FAILURE;
        }
    }
}
