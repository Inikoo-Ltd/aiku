<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Jan 2026 12:15:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;

class CreateDiscretionaryCharges extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop): ?Charge
    {

        if ($shop->charges()->where('type', ChargeTypeEnum::DISCRETIONARY->value)->exists()) {
            return $shop->charges()->where('type', ChargeTypeEnum::DISCRETIONARY->value)->first();
        }



        data_set($modelData, 'type', ChargeTypeEnum::DISCRETIONARY);
        data_set($modelData, 'trigger', ChargeTriggerEnum::SELECTED_BY_USER);
        data_set($modelData, 'code', 'Disc');
        data_set($modelData, 'name', 'Discretionary charge');
        data_set($modelData, 'description', 'Discretionary charge');
        data_set($modelData, 'status', true);
        data_set($modelData, 'state', ChargeStateEnum::ACTIVE);
        data_set($modelData, 'label', 'Discretionary charge');


        return StoreCharge::make()->action($shop, $modelData);
    }


    public function getCommandSignature(): string
    {
        return 'create_discretionary_charges';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(): int
    {

        foreach (Shop::where('state', '!=', ShopStateEnum::CLOSED)->get() as $shop) {
            $this->handle($shop);
        }


        return 0;
    }

}
