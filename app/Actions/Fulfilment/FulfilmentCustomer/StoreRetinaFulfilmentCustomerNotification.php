<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-09h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\Fulfilment\FulfilmentCustomer\Notification\RetinaFulfilmentCustomerNotification;
use App\Actions\OrgAction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;

class StoreRetinaFulfilmentCustomerNotification extends OrgAction
{
    public function handle(PalletDelivery|PalletReturn $parent)
    {
        $parent
            ->fulfilmentCustomer
            ->notify(new RetinaFulfilmentCustomerNotification($parent));
    }
}
