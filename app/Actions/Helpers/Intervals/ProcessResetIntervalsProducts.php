<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jan 2026 15:15:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateDeliveryNotesIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrderIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsProducts
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'aiku:process-reset-intervals-products';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (
            Product::whereNot('state', ProductCategoryStateEnum::DISCONTINUED)->get() as $product
        ) {
            AssetHydrateSalesIntervals::dispatch(
                assetID: $product->asset_id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateOrderIntervals::dispatch(
                assetID: $product->asset_id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateDeliveryNotesIntervals::dispatch(
                assetID: $product->asset_id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateInvoiceIntervals::dispatch(
                assetID: $product->asset_id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateInvoicedCustomersIntervals::dispatch(
                assetID: $product->asset_id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }
    }

}
