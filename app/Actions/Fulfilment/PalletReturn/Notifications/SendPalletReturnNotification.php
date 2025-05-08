<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:49:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\Notifications;

use App\Actions\Fulfilment\FulfilmentCustomer\StoreRetinaFulfilmentCustomerNotification;
use App\Actions\OrgAction;
use App\Events\BroadcastFulfilmentCustomerNotification;
use App\Models\Fulfilment\PalletReturn;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SendPalletReturnNotification extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PalletReturn $palletReturn): void
    {
        $palletReturn->refresh();

        broadcast(new BroadcastFulfilmentCustomerNotification(
            $palletReturn->group,
            $palletReturn
        ))->toOthers();

        StoreRetinaFulfilmentCustomerNotification::dispatch($palletReturn);
    }
}
