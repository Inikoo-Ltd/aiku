<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 20:31:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Billables\Charge;
use App\Models\Billables\ShippingZone;
use App\Models\Ordering\Adjustment;

trait WithStoreNoProductTransaction
{
    private function prepareChargeTransaction(?Charge $charge, $modelData): array
    {
        data_set($modelData, 'model_type', 'Charge');
        if ($charge) {
            data_set($modelData, 'model_id', $charge->id);
            data_set($modelData, 'asset_id', $charge->id);
            data_set($modelData, 'historic_asset_id', $charge->current_historic_asset_id);
        }

        return $modelData;
    }

    private function prepareShippingTransaction(?ShippingZone $shippingZone, $modelData): array
    {
        data_set($modelData, 'model_type', 'ShippingZone');
        if ($shippingZone) {
            data_set($modelData, 'model_id', $shippingZone->id);
            data_set($modelData, 'asset_id', $shippingZone->id);
            data_set($modelData, 'historic_asset_id', $shippingZone->current_historic_asset_id);
        }

        return $modelData;
    }

    private function prepareAdjustmentTransaction(Adjustment $adjustment, $modelData): array
    {
        data_set($modelData, 'model_type', 'Adjustment');
        data_set($modelData, 'model_id', $adjustment->id);

        $net   = $adjustment->net_amount;
        $gross = $net;

        data_set($modelData, 'gross_amount', $gross, overwrite: false);
        data_set($modelData, 'net_amount', $net, overwrite: false);

        return $modelData;
    }

}
