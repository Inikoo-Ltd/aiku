<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 18:51:38 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Iris\Disclosure;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowUnderConstruction
{
    use AsController;

    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        if ($request->get('website')->status) {
            return Redirect::route('iris.home');

        }

        return Inertia::render('Disclosure/UnderConstruction');
    }
}
