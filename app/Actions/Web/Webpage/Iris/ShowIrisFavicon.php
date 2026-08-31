<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisFavicon
{
    use AsAction;

    public function asController(ActionRequest $request): RedirectResponse
    {
        return redirect($request->input('favicons')['48'])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
