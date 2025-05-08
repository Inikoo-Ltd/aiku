<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:54:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery\Notifications;

use App\Actions\Fulfilment\FulfilmentCustomer\StoreRetinaFulfilmentCustomerNotification;
use App\Actions\OrgAction;
use App\Events\BroadcastFulfilmentCustomerNotification;
use App\Models\Fulfilment\PalletDelivery;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SendPalletDeliveryNotification extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'urgent';

    public function handle(PalletDelivery $palletDelivery): void
    {
        $palletDelivery->refresh();

        broadcast(new BroadcastFulfilmentCustomerNotification(
            $palletDelivery->group,
            $palletDelivery
        ))->toOthers();

        StoreRetinaFulfilmentCustomerNotification::dispatch($palletDelivery);
    }
}
