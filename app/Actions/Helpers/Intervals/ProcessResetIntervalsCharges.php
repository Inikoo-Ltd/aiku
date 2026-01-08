<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateDeliveryNotesIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrderIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Models\Billables\Charge;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsCharges
{
    use AsAction;

    public string $jobQueue = 'default-long';

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (
            Charge::whereNot('state', ChargeStateEnum::DISCONTINUED)->get() as $charge
        ) {
            AssetHydrateSalesIntervals::dispatch(
                assetID: $charge->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateOrderIntervals::dispatch(
                assetID: $charge->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateDeliveryNotesIntervals::dispatch(
                assetID: $charge->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateInvoiceIntervals::dispatch(
                assetID: $charge->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            AssetHydrateInvoicedCustomersIntervals::dispatch(
                assetID: $charge->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
        }
    }
}
