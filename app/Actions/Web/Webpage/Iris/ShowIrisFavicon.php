<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Browsers ask for /favicon.ico and iPhones for /apple-touch-icon*.png (home screen and bookmarks)
 * whatever the page links to, so both go to the website's own icon: 48px for the favicon, 180px for
 * the touch icons. The edge keeps these redirects for the hour they allow (edge-cache-rules.json).
 */
class ShowIrisFavicon
{
    use AsAction;

    public function asController(ActionRequest $request): RedirectResponse
    {
        return redirect($request->input('favicons')[$request->is('favicon.ico') ? '48' : '180'])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
