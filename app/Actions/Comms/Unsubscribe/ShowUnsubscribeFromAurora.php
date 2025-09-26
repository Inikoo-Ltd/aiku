<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 15:34:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Unsubscribe;

use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowUnsubscribeFromAurora extends IrisAction
{
    use WithActionUpdate;



    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->htmlResponse();
    }

    public function htmlResponse(): \Illuminate\Http\Response|Response
    {
        return Inertia::render('UnsubscribeFromAurora', [
            'title'   => __("Unsubscribe"),
            'message' => [
                'confirmationTitle' => __("Are you sure to unsubscribe?"),

                'button'             => __('Click here to unsubscribe'),
                'successTitle'       => __("Unsubscription successful"),
                'successDescription' => __("You have been unsubscribed, sorry for any inconvenience caused."),
                'error'              => __("Something went wrong, call us."),

            ]
        ]);
    }
}
