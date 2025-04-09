<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 19:55:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\PalletDelivery\CalculatePalletDeliveryNet;
use App\Actions\Fulfilment\PalletReturn\CalculatePalletReturnNet;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementCauseStateEnum;
use App\Models\Fulfilment\FulfilmentTransaction;
use App\Models\Fulfilment\PalletDelivery;

class SetClausesInFulfilmentTransaction extends OrgAction
{
    public function handle(FulfilmentTransaction $fulfilmentTransaction)
    {
        $rentalAgreementClauses = $fulfilmentTransaction->parent->fulfilmentCustomer->rentalAgreementClauses()
                                    ->where('state', RentalAgreementCauseStateEnum::ACTIVE)
                                    ->get();
        $percentageOff          = 0;
        $found                  = false;
        foreach ($rentalAgreementClauses as $clause) {
            if ($clause->asset_id === $fulfilmentTransaction->asset_id) {
                data_set($modelData, 'rental_agreement_clause_id', $clause->id);
                $percentageOff = $clause->percentage_off / 100;
                $found         = true;
                break;
            }
        }

        if (!$found) {
            data_set($modelData, 'rental_agreement_clause_id', null);
        }

        $net = $fulfilmentTransaction->asset->price * $fulfilmentTransaction->quantity;
        $gross = $fulfilmentTransaction->asset->price * $fulfilmentTransaction->quantity;
        $net -= $net                                * $percentageOff;

        data_set($modelData, 'net_amount', $net);
        data_set($modelData, 'gross_amount', $gross);
        data_set($modelData, 'grp_net_amount', $net * $fulfilmentTransaction->grp_exchange);
        data_set($modelData, 'org_net_amount', $net * $fulfilmentTransaction->org_exchange);

        $fulfilmentTransaction->update($modelData);

        if ($fulfilmentTransaction->parent instanceof PalletDelivery) {
            CalculatePalletDeliveryNet::run($fulfilmentTransaction->parent);
        } else {
            CalculatePalletReturnNet::run($fulfilmentTransaction->parent);
        }

    }
}
