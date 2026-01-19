<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Nov 2023 14:41:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\DispatchedEmail;
use Inertia\Inertia;
use Inertia\Response;

class ShowUnsubscribeMailshot
{
    use WithActionUpdate;

    public function handle(DispatchedEmail $dispatchedEmail): DispatchedEmail
    {
        return $dispatchedEmail;
    }

    public function asController(DispatchedEmail $dispatchedEmail): DispatchedEmail
    {
        return $this->handle($dispatchedEmail);
    }

    public function htmlResponse(): Response
    {
        return Inertia::render('UnsubscribeMailshot', [
            'title'           => __("Unsubscribe"),
            'message'         => [
                'confirmationTitle'       => __("Are you sure to unsubscribe?"),
                'successTitle'            => __("Unsubscription successful"),
                'successDescription'      => __("You have been unsubscribed, sorry for any inconvenience caused."),
                'button'                  => __('Click here to unsubscribe'),

            ]
        ]);
    }
}
