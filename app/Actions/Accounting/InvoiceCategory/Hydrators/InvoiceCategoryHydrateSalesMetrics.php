<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 14:17:09 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceCategory\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSalesMetrics;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\InvoiceCategorySalesMetrics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceCategoryHydrateSalesMetrics implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSalesMetrics;

    public string $commandSignature = 'hydrate:invoice-category-sales-metrics {invoice_category}';

    public function getJobUniqueId(InvoiceCategory $invoiceCategory, Carbon $date): string
    {
        return $invoiceCategory->id . '-' . $date->format('YmdHis');
    }

    public function asCommand(Command $command): void
    {
        $invoiceCategory = InvoiceCategory::where('slug', $command->argument('invoice_category'))->first();

        if (!$invoiceCategory) {
            return;
        }

        $today = Carbon::today();

        $this->handle($invoiceCategory, $today);
    }

    public function handle(InvoiceCategory $invoiceCategory, Carbon $date): void
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $metrics = $this->getSalesMetrics([
            'context' => ['invoice_category_id' => $invoiceCategory->id],
            'start'   => $dayStart,
            'end'     => $dayEnd,
            'fields'  => [
                'invoices',
                'refunds',
                'sales_grp_currency',
                'sales_invoice_category_currency',
                'revenue_grp_currency',
                'revenue_invoice_category_currency',
                'lost_revenue_grp_currency',
                'lost_revenue_invoice_category_currency'
            ]
        ]);

        InvoiceCategorySalesMetrics::updateOrCreate(
            [
                'group_id' => $invoiceCategory->group_id,
                'organisation_id' => $invoiceCategory->organisation_id,
                'invoice_category_id' => $invoiceCategory->id,
                'date' => $dayStart
            ],
            $metrics
        );
    }
}
