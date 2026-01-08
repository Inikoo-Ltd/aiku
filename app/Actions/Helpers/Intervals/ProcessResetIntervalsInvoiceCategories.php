<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateOrderingIntervals;
use App\Actions\Accounting\InvoiceCategory\Hydrators\InvoiceCategoryHydrateSalesIntervals;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Models\Accounting\InvoiceCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsInvoiceCategories
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-invoice-categories';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (
            InvoiceCategory::whereIn('state', [
                InvoiceCategoryStateEnum::ACTIVE,
                InvoiceCategoryStateEnum::COOLDOWN
            ])->get() as $invoiceCategory
        ) {
            InvoiceCategoryHydrateSalesIntervals::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            InvoiceCategoryHydrateOrderingIntervals::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }

        foreach (
            InvoiceCategory::whereNotIn('state', [
                InvoiceCategoryStateEnum::ACTIVE,
                InvoiceCategoryStateEnum::COOLDOWN
            ])->get() as $invoiceCategory
        ) {
            InvoiceCategoryHydrateSalesIntervals::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');

            InvoiceCategoryHydrateOrderingIntervals::dispatch(
                invoiceCategory: $invoiceCategory,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinute())->onQueue('low-priority');
        }
    }
}
