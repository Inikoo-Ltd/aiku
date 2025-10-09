<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 17:35:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowHome
{
    use AsController;

    public function handle(): Response
    {
        return Inertia::render('Home');
    }

}
