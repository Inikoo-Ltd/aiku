<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Apr 2024 19:36:27 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Listeners;

use App\Events\BroadcastFulfilmentCustomerNotification;
use App\Notifications\MeasurementShareNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class MeasurementSharedListener implements ShouldQueue
{
    public function __construct()
    {
        //
    }

    public function handle(BroadcastFulfilmentCustomerNotification $event): void
    {
        foreach ($event->group->users as $user) {
            $user->notify(new MeasurementShareNotification($event->data));
        }
    }
}
