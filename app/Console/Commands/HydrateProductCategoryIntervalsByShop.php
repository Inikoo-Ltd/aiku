<?php

namespace App\Console\Commands;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateInvoiceIntervals;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class HydrateProductCategoryIntervalsByShop extends Command
{
    protected $signature = 'hydrate:product-category-intervals
                            {shop : Shop slug}
                            {--type=all : Type to hydrate (department|family|sub-department|all)}
                            {--dispatch : Dispatch to queue instead of running synchronously}';

    protected $description = 'Hydrate ProductCategory invoice intervals by shop';

    public function handle(): int
    {
        $shopSlug = $this->argument('shop');
        $type = $this->option('type');
        $useQueue = $this->option('dispatch');

        // Find shop
        $shop = Shop::where('slug', $shopSlug)->first();

        if (!$shop) {
            $this->error("Shop with slug '{$shopSlug}' not found!");
            return 1;
        }

        $this->info("Hydrating ProductCategory intervals for shop: {$shop->name}");
        $this->newLine();

        // Get product categories based on type
        $query = ProductCategory::where('shop_id', $shop->id);

        if ($type !== 'all') {
            $typeEnum = match($type) {
                'department' => 'department',
                'family' => 'family',
                'sub-department' => 'sub_department',
                default => null
            };

            if (!$typeEnum) {
                $this->error("Invalid type: {$type}. Use: department, family, sub-department, or all");
                return 1;
            }

            $query->where('type', $typeEnum);
        }

        $categories = $query->get();
        $total = $categories->count();

        if ($total === 0) {
            $this->warn("No product categories found for shop '{$shop->name}' with type '{$type}'");
            return 0;
        }

        $this->info("Found {$total} product categories to hydrate");

        if (!$this->confirm('Continue with hydration?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $this->newLine();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($categories as $category) {
            try {
                // Ensure orderingIntervals record exists
                if (!$category->orderingIntervals) {
                    $category->orderingIntervals()->create([]);
                }

                if ($useQueue) {
                    // Dispatch to queue
                    ProductCategoryHydrateInvoiceIntervals::dispatch($category->id);
                } else {
                    // Run synchronously
                    ProductCategoryHydrateInvoiceIntervals::run($category->id);
                }

                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to hydrate {$category->code}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Hydration completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $success],
                ['Failed', $failed],
                ['Total', $total],
            ]
        );

        if ($useQueue) {
            $this->info("\nJobs have been dispatched to queue. Check queue workers for progress.");
        }

        return 0;
    }
}
