<?php

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\CRM\TrafficSource\SeedTrafficSources;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSource;
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

        $abbreviations = $this->extractAbbreviations($trafficSourcesData);

        if (empty($abbreviations)) {
            return;
        }

        $typeValues = [];

        foreach ($abbreviations as $abbreviation) {
            $enum = TrafficSourcesTypeEnum::fromAbbr($abbreviation);
            if ($enum !== null) {
                $typeValues[] = $enum->value;
            }
        }

        $typeValues = array_unique($typeValues);

        if (empty($typeValues)) {
            return;
        }

        $trafficSources = TrafficSource::where('shop_id', $customer->shop_id)
            ->whereIn('type', $typeValues)
            ->get();

        if ($trafficSources->isEmpty()) {
            return;
        }

        $share = round(1 / $trafficSources->count(), 2);

        foreach ($trafficSources as $trafficSource) {
            $customer->trafficSources()->syncWithoutDetaching([
                $trafficSource->id => ['share' => $share],
            ]);

            TrafficSourceHydrateCustomers::dispatch($trafficSource);
        }
    }

    private function extractAbbreviations(string $data): array
    {
        $segments = preg_split('/[|,]/', $data);
        $abbreviations = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (blank($segment)) {
                continue;
            }

            $withoutTimestamp = ltrim($segment, '0123456789');

            if (strlen($withoutTimestamp) === 0) {
                continue;
            }

            $abbreviations[] = $withoutTimestamp[0];
        }

        return $abbreviations;
    }
}
