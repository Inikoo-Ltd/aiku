<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 15:25:18 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\UIDummies;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

trait IsDummy
{
    use AsAction;

    public function asController(ActionRequest $request): ActionRequest
    {
        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Devel/Dummy',
            [

                'title' => __('dummy'),
                'pageHead' => [
                    'title' => $request->route()->getName(),
                ],

            ]
        );
    }
}
