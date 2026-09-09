<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait WithSystemPageRedirect
{
    /**
     * Redirect to the website's live Iris system page, or null to fall back to the built in form.
     */
    protected function redirectToSystemPage(?Webpage $webpage, Request $request): ?RedirectResponse
    {
        if (!$webpage || $webpage->state != WebpageStateEnum::LIVE || !$webpage->canonical_url) {
            return null;
        }

        $url = ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);

        if ($request->has('ref')) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query(['ref' => $request->query('ref')]);
        }

        return redirect()->to($url);
    }
}
