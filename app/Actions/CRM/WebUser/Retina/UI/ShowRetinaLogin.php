<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowRetinaLogin
{
    use AsController;


    public function handle(): Response
    {
        return Inertia::render('Auth/RetinaLogin', [
            'google'    => [
                'client_id' => '627235140872-2pbbrb6mlnj5g06us8t9fsph25h0je7f.apps.googleusercontent.com'
            ]
        ]);
    }
}
