<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 14:17:10 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesMetrics;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairInvoiceCategorySalesMetrics
{
    use AsAction;

    public string $commandSignature = 'repair:invoice-category-sales-metrics';

    public function asCommand(Command $command): int
    {
        $invoiceCategories = InvoiceCategory::all();

        $firstOrder = Order::withTrashed()
            ->orderBy('date')
            ->first();

        if (!$firstOrder) {
            $command->error('No orders found. Nothing to repair.');
            return 0;
        }

        $start = Carbon::parse($firstOrder->date)->startOfDay();
        $end   = Carbon::now()->endOfDay();

        $period = CarbonPeriod::create($start, $end);

        $totalDays  = iterator_count($period);
        $totalInvoiceCategories = $invoiceCategories->count();

        $totalSteps = $totalDays * $totalInvoiceCategories;

        $command->info("Repairing Invoice Category Sales Metrics...");
        $command->info("Total days: $totalDays | Invoice Categories: $totalInvoiceCategories | Steps: $totalSteps");

        $bar = $command->getOutput()->createProgressBar($totalSteps);
        $bar->setFormat('debug');
        $bar->start();

        foreach ($invoiceCategories as $invoiceCategory) {
            foreach ($period as $date) {
                InvoiceCategoryHydrateSalesMetrics::run($invoiceCategory, $date);
                $bar->advance();
            }
        }

        $bar->finish();
        $command->info("");
        $command->info("Completed.");

        return 0;
    }
}
