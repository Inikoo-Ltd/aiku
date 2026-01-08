<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateInvoiceIntervals;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateSalesIntervals;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsProductCategories
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'aiku:process-reset-intervals-product-categories';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (
            ProductCategory::whereIn('state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])->get() as $productCategory
        ) {
            ProductCategoryHydrateSalesIntervals::dispatch(
                productCategoryId: $productCategory->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            ProductCategoryHydrateInvoiceIntervals::dispatch(
                productCategoryId: $productCategory->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }

        foreach (
            ProductCategory::whereNotIn('state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])->get() as $productCategory
        ) {
            ProductCategoryHydrateSalesIntervals::dispatch(
                productCategoryId: $productCategory->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');

            ProductCategoryHydrateInvoiceIntervals::dispatch(
                productCategoryId: $productCategory->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            )->delay(now()->addMinutes(60))->onQueue('low-priority');
        }
    }
}
