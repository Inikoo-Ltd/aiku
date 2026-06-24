<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 18:51:38 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Iris\Disclosure;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowUnderMaintenance
{
    use AsController;

    public function handle(): Response
    {
        return Inertia::render('Disclosure/UnderMaintenance', []);
    }
}
