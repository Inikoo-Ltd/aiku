<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 20:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteHit
{
    use AsAction;


    public function asController(ActionRequest $request): void
    {
        $website = request()->website;

        $appName = request()->header('X-Analytics-App');
        if (!$appName) {
            return;
        }

        $metrics = [
            'org'       => $website->organisation_id,
            'website'   => $website->slug,
            'webpage'   => request()->header('X-Analytics-Webpage'),
            'device'    => request()->header('sec-ch-ua-form-factors'),
            'country'   => request()->header('CF-IPCountry') ?? 'XX',
            'logged_in' => $request->user() !== null,
            'url'       => $request->header('referer'),
            'app'       => $appName
        ];
        ProcessWebsiteHit::dispatch($metrics, $request->userAgent());
    }

}
