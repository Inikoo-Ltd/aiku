<?php

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\AttachTrafficSourcesToModel;
use App\Actions\CRM\TrafficSource\ParseTrafficSourceTouches;
use App\Actions\CRM\TrafficSource\SeedTrafficSources;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;

class BackfillCustomerTrafficSources extends Command
{
    protected $signature = 'traffic-source:backfill-customers
                           {--shop= : Only backfill for a specific shop slug}
                           {--dry-run : Count records without making changes}';

    protected $description = 'Backfill customer-traffic_source pivot records from existing traffic_sources text column';

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

        $shops = Shop::whereHas('customers', function ($q) {
            $q->whereNotNull('traffic_sources')
              ->where('traffic_sources', '!=', '');
        })->get();

        if ($shops->isEmpty()) {
            $this->info('No shops found with customers that have traffic_sources data.');

            return Command::SUCCESS;
        }

        $this->info("Found {$shops->count()} shops with traffic sources data to backfill.");

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
        $this->line("  Traffic sources seeded for shop.");

        $query = Customer::where('shop_id', $shop->id)
            ->whereNotNull('traffic_sources')
            ->where('traffic_sources', '!=', '');

        $total = $query->count();

        if ($total === 0) {
            $this->line("  No customers with traffic_sources data.");

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line("  Customers to backfill: {$total}");

            return Command::SUCCESS;
        }

        $processed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function ($customers) use (&$processed, $bar) {
            foreach ($customers as $customer) {
                $this->processCustomer($customer);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("  Backfilled {$processed} customers for {$shop->name}.");

        return Command::SUCCESS;
    }

    private function processCustomer(Customer $customer): void
    {
        $trafficSourcesData = $customer->traffic_sources;

        if (blank($trafficSourcesData)) {
            return;
        }

        $touches = ParseTrafficSourceTouches::run($trafficSourcesData);

        if (empty($touches)) {
            return;
        }

        AttachTrafficSourcesToModel::run($customer, $customer->shop_id, $touches);
    }
}
