<?php

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class FixPurchaseOrderData
{
    use AsAction;

    public string $commandSignature = 'fix:purchase-order-data
        {--dry-run : Run without making actual changes}
        {--limit= : Limit the number of records to process}';

    public string $commandDescription = 'Fix broken PurchaseOrder data where count columns show 0 but actual transaction records exist.';

    protected int $totalProcessed = 0;
    protected int $brokenFound = 0;
    protected int $fixedCount = 0;
    protected int $errorCount = 0;
    protected array $errors = [];

    public function handle(bool $dryRun = false, ?int $limit = null, ?Command $command = null): array
    {
        $this->resetCounters();

        $query = PurchaseOrder::query()->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $purchaseOrders = $query->get();
        $total = $purchaseOrders->count();

        if ($command) {
            $command->info('');
            $command->info('🔧 Starting PurchaseOrder data fix...');
            if ($dryRun) {
                $command->warn('   ⚠️  DRY-RUN MODE: No changes will be made to the database');
            }
            $command->info('');
        }

        $progressCallback = null;
        if ($command) {
            $bar = $command->getOutput()->createProgressBar($total);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
            $bar->start();
            $progressCallback = function () use ($bar) {
                $bar->advance();
            };
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            try {
                $this->processPurchaseOrder($purchaseOrder, $dryRun, $command);
            } catch (Throwable $e) {
                $this->errorCount++;
                $this->errors[] = [
                    'reference' => $purchaseOrder->reference,
                    'id' => $purchaseOrder->id,
                    'error' => $e->getMessage()
                ];

                Log::error('FixPurchaseOrderData error', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'reference' => $purchaseOrder->reference,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->totalProcessed++;

            if ($progressCallback) {
                $progressCallback();
            }
        }

        if ($command) {
            $bar->finish();
            $command->info('');
            $command->info('');
            $this->displaySummary($command, $dryRun);
        }

        return $this->getSummary();
    }

    protected function processPurchaseOrder(PurchaseOrder $purchaseOrder, bool $dryRun, ?Command $command): void
    {
        $actualTransactionCount = $purchaseOrder->purchaseOrderTransactions()->count();
        $storedTransactionCount = $purchaseOrder->number_purchase_order_transactions;
        $isBroken = $storedTransactionCount !== $actualTransactionCount;

        if (!$isBroken) {
            return;
        }

        $this->brokenFound++;

        if ($command) {
            $command->info('');
            $command->error("❌ Found broken PurchaseOrder: {$purchaseOrder->reference}");
            $command->line("   Database shows: {$storedTransactionCount} transactions");
            $command->line("   Actual count: {$actualTransactionCount} transactions");
        }

        if (!$dryRun) {
            PurchaseOrderHydrateTransactions::run($purchaseOrder);
            $purchaseOrder->refresh();
            $this->fixedCount++;

            if ($command) {
                $command->info("   ✅ Fixed! New count: {$purchaseOrder->number_purchase_order_transactions}");
            }
        } else {
            if ($command) {
                $command->warn("   ⏸️  Would fix (dry-run mode)");
            }
        }
    }

    protected function displaySummary(Command $command, bool $dryRun): void
    {
        $command->info('--SUMMARY--');
        $command->info("Total PurchaseOrders processed: {$this->totalProcessed}");
        $command->info("Broken PurchaseOrders found: {$this->brokenFound}");
        $okCount = $this->totalProcessed - $this->brokenFound;
        $command->info("PurchaseOrders OK: {$okCount}");

        if (!$dryRun) {
            $command->info("PurchaseOrders fixed: {$this->fixedCount}");
        }

        if ($this->errorCount > 0) {
            $command->error("Errors encountered: {$this->errorCount}");
            foreach ($this->errors as $error) {
                $command->error("  - {$error['reference']} (ID: {$error['id']}): {$error['error']}");
            }
        }

        $command->info('');

        if ($dryRun) {
            if ($this->brokenFound > 0) {
                $command->warn("⚠️  {$this->brokenFound} broken record(s) found. Run without --dry-run to fix them.");
            } else {
                $command->info('✅ No broken data found!');
            }
        } else {
            if ($this->brokenFound > 0 && $this->fixedCount === $this->brokenFound) {
                $command->info('✅ All broken data has been fixed!');
            } elseif ($this->brokenFound === 0) {
                $command->info('✅ No broken data found!');
            } else {
                $command->warn("⚠️  Some records could not be fixed. Check errors above.");
            }
        }
    }

    protected function resetCounters(): void
    {
        $this->totalProcessed = 0;
        $this->brokenFound = 0;
        $this->fixedCount = 0;
        $this->errorCount = 0;
        $this->errors = [];
    }

    protected function getSummary(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'broken_found' => $this->brokenFound,
            'fixed_count' => $this->fixedCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors,
        ];
    }

    public function asCommand(Command $command): int
    {
        $dryRun = (bool) $command->option('dry-run');
        $limit = $command->option('limit') ? (int) $command->option('limit') : null;

        $summary = $this->handle($dryRun, $limit, $command);

        return $summary['error_count'] > 0 ? 1 : 0;
    }
}
