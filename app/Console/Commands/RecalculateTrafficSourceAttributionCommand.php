<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\TrafficSource\ProcessTrafficSourceShare;
use App\Actions\CRM\TrafficSource\RecalculateTrafficSourceAttribution;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class RecalculateTrafficSourceAttributionCommand extends Command
{
    protected $signature = 'traffic-source:recalculate-attribution
                           {--shop= : Only recalculate for a specific shop slug}
                           {--type=both : Recalculate "customers", "orders" or "both"}
                           {--model=linear : Attribution model: first_touch, last_touch, last_non_direct_touch, last_paid_touch, linear}
                           {--from= : Only records created on/after this date (Y-m-d)}
                           {--to= : Only records created on/before this date (Y-m-d)}
                           {--dry-run : Count records without making changes}';

    protected $description = 'Recalculate traffic source attribution pivot rows from raw touch history for customers and/or orders';

    public function handle(): int
    {
        $model = $this->option('model');

        if (!in_array($model, [
            ProcessTrafficSourceShare::ATTRIBUTION_FIRST_TOUCH,
            ProcessTrafficSourceShare::ATTRIBUTION_LAST_TOUCH,
            ProcessTrafficSourceShare::ATTRIBUTION_LAST_NON_DIRECT,
            ProcessTrafficSourceShare::ATTRIBUTION_LAST_PAID_TOUCH,
            ProcessTrafficSourceShare::ATTRIBUTION_LINEAR,
        ], true)) {
            $this->error("Unknown attribution model '{$model}'.");

            return Command::FAILURE;
        }

        $shop = null;

        if ($shopSlug = $this->option('shop')) {
            $shop = Shop::where('slug', $shopSlug)->first();

            if (!$shop) {
                $this->error("Shop '{$shopSlug}' not found.");

                return Command::FAILURE;
            }
        }

        $type = $this->option('type');

        if (in_array($type, ['customers', 'both'], true)) {
            $this->recalculate(Customer::class, $shop, $model);
        }

        if (in_array($type, ['orders', 'both'], true)) {
            $this->recalculate(Order::class, $shop, $model);
        }

        return Command::SUCCESS;
    }

    private function recalculate(string $modelClass, ?Shop $shop, string $attributionModel): void
    {
        $label = class_basename($modelClass);

        $query = $modelClass::query()
            ->whereNotNull('traffic_sources')
            ->where('traffic_sources', '!=', '');

        if ($shop) {
            $query->where('shop_id', $shop->id);
        }

        if ($from = $this->option('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $this->option('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $total = $query->count();

        $this->info("{$label}: {$total} record(s) matched (model: {$attributionModel}).");

        if ($total === 0 || $this->option('dry-run')) {
            return;
        }

        $processed = 0;
        $bar       = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($records) use (&$processed, $bar, $attributionModel) {
            foreach ($records as $record) {
                RecalculateTrafficSourceAttribution::run($record, $attributionModel);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("{$label}: recalculated {$processed} record(s).");
    }
}
