<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisBlogDashboard
{
    use AsAction;

    public function asController(ActionRequest $request): Response
    {
        return $this->handle();
    }


    public function handle(): Response
    {
        return Inertia::render(
            'BlogDashboard'
        );
    }
}
