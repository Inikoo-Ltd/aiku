<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 15:18:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\IrisAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaRegisterChooseMethod extends IrisAction
{
    public function handle(): Response
    {
        return Inertia::render(
            'Auth/RegisterSelectMethod',
            [
                'google' => [
                    'client_id' => config('services.google.client_id')
                ]
            ]
        );
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle();
    }

}
