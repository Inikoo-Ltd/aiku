<?php

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateFirstAvailableAt;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductsFirstAvailableAt
{
    use AsAction;

    public string $jobQueue = 'sales_slave_historic';

    /**
     * Backfills first_available_at on a product launched before the NEW IN badge existed, from its
     * first stock date or else its creation date, so it does not show up as new.
     * A product never launched (coming soon, in process) keeps it empty and gets it on its launch.
     * A product stamped later than it was first ordered, invoiced, or live on the website with stock, as old products
     * touched before the backfill were, is moved back to the earliest of these.
     * A never sold product stamped on the backfill day, but created long before it, gets its creation date like the backfill would have given it.
     */
    public function handle(Product $product, ?string $backfillDay = null): void
    {
        if ($product->first_available_at) {
            $firstSoldAt = ProductHydrateFirstAvailableAt::firstSoldAt($product);

            $earliestProof = ProductHydrateFirstAvailableAt::earliest([
                $firstSoldAt,
                $this->liveWithStockSince($product),
                !$firstSoldAt && $backfillDay && $this->wasStampedOnBackfillDayLongAfterCreation($product, $backfillDay) ? $product->created_at : null,
            ]);

            if ($earliestProof && $earliestProof->lt($product->first_available_at)) {
                $product->update(['first_available_at' => $earliestProof]);
            }

            return;
        }

        if ($this->isNotLaunched($product) || $product->isStockedOnlyElsewhere()) {
            return;
        }

        $product->update(['first_available_at' => $product->first_in_stock_at ?? $product->created_at]);
    }

    private function liveWithStockSince(Product $product): ?Carbon
    {
        $liveAt = $product->webpage?->live_at;

        if (!$liveAt || !$product->back_in_stock_since) {
            return null;
        }

        $liveWithStockSince = Carbon::parse($product->back_in_stock_since)->max($liveAt);
        $stockRanOutAt      = $product->out_of_stock_since ? Carbon::parse($product->out_of_stock_since) : null;

        if ($stockRanOutAt && $stockRanOutAt->gt(Carbon::parse($product->back_in_stock_since)) && $stockRanOutAt->lte($liveWithStockSince)) {
            return null;
        }

        return $liveWithStockSince;
    }

    private function wasStampedOnBackfillDayLongAfterCreation(Product $product, string $backfillDay): bool
    {
        return $product->first_available_at->isSameDay(Carbon::parse($backfillDay))
            && $product->created_at->lt($product->first_available_at->copy()->subDays(ProductBadgeEnum::NEW_IN_DAYS));
    }

    private function isNotLaunched(Product $product): bool
    {
        return in_array($product->status, [ProductStatusEnum::COMING_SOON, ProductStatusEnum::IN_PROCESS])
            || $product->state === ProductStateEnum::IN_PROCESS;
    }

    public string $commandSignature = 'repair:products_first_available_at {--a|async : Dispatch each product as a job instead of repairing it here} {--stamped-since= : Also check products stamped on or after this date against their first order, invoice, or day live with stock} {--backfill-day= : Give never sold products stamped on this day, and created long before it, their creation date}';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();

        $async        = (bool) $command->option('async');
        $stampedSince = $command->option('stamped-since');
        $backfillDay  = $command->option('backfill-day');
        $count        = 0;

        $query = Product::query()
            ->where(function ($query) use ($stampedSince, $backfillDay) {
                $query->where(function ($query) {
                    $query->whereNull('first_available_at')
                        ->whereNotIn('status', [ProductStatusEnum::COMING_SOON, ProductStatusEnum::IN_PROCESS])
                        ->where('state', '!=', ProductStateEnum::IN_PROCESS);
                });

                if ($stampedSince) {
                    $query->orWhere('first_available_at', '>=', $stampedSince);
                }

                if ($backfillDay) {
                    $query->orWhereBetween('first_available_at', [Carbon::parse($backfillDay)->startOfDay(), Carbon::parse($backfillDay)->endOfDay()]);
                }
            });

        $progressBar = $command->getOutput()->createProgressBar($query->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $query->chunkById(5000, function ($products) use ($async, $backfillDay, &$count, $progressBar) {
            foreach ($products as $product) {
                if ($async) {
                    self::dispatch($product, $backfillDay);
                } else {
                    $this->handle($product, $backfillDay);
                }
                $count++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
        $command->info($async ? "{$count} jobs dispatched to Horizon queue {$this->jobQueue}" : "products repaired: {$count}");
    }
}
