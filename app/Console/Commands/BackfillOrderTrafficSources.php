<?php

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\AttachTrafficSourcesToModel;
use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Actions\CRM\TrafficSource\SeedTrafficSources;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class BackfillOrderTrafficSources extends Command
{
    protected $signature = 'traffic-source:backfill-orders
                           {--shop= : Only backfill for a specific shop slug}
                           {--dry-run : Count records without making changes}';

    protected $description = 'Backfill order-traffic_source pivot records from the order\'s or its customer\'s existing traffic_sources text column';

    public function handle(): int
    {
        if ($shopSlug = $this->option('shop')) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if (!$shop) {
                $this->error("Shop '{$shopSlug}' not found.");

                return Command::FAILURE;
            }

            return $this->backfillShop($shop);
        }

        $shops = Shop::whereHas('orders', function ($q) {
            $q->whereNotNull('traffic_sources')
              ->where('traffic_sources', '!=', '')
              ->orWhereHas('customer', function ($q) {
                  $q->whereNotNull('traffic_sources')
                    ->where('traffic_sources', '!=', '');
              });
        })->get();

        if ($shops->isEmpty()) {
            $this->info('No shops found with orders that have traffic_sources data.');

            return Command::SUCCESS;
        }

        $this->info("Found {$shops->count()} shops with orders to backfill.");

        foreach ($shops as $shop) {
            $result = $this->backfillShop($shop);

            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        return Command::SUCCESS;
    }

    private function backfillShop(Shop $shop): int
    {
        $this->newLine();
        $this->info("Processing shop: {$shop->name} ({$shop->slug})");

        SeedTrafficSources::run($shop);
        $this->line('  Traffic sources seeded for shop.');

        $query = Order::where('shop_id', $shop->id)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('traffic_sources')
                      ->where('traffic_sources', '!=', '');
                })->orWhereHas('customer', function ($q) {
                    $q->whereNotNull('traffic_sources')
                      ->where('traffic_sources', '!=', '');
                });
            });

        $total = $query->count();

        if ($total === 0) {
            $this->line('  No orders with traffic_sources data.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("  Orders to backfill: {$total}");

            return Command::SUCCESS;
        }

        $processed = 0;
        $bar       = $this->output->createProgressBar($total);
        $bar->start();

        $query->with('customer')->chunkById(100, function ($orders) use (&$processed, $bar) {
            foreach ($orders as $order) {
                $this->processOrder($order);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  Backfilled {$processed} orders for {$shop->name}.");

        return Command::SUCCESS;
    }

    private function processOrder(Order $order): void
    {
        $rawTouchesData = $order->traffic_sources ?: $order->customer?->traffic_sources;

        if (blank($rawTouchesData)) {
            return;
        }

        $touches = ParseTrafficSourceTouches::run($rawTouchesData);

        if (empty($touches)) {
            return;
        }

        AttachTrafficSourcesToModel::run($order, $order->shop_id, $touches);
    }
}
