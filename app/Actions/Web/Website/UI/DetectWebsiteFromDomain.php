<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Feb 2024 17:10:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Exceptions\IrisWebsiteNotFound;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsObject;

class DetectWebsiteFromDomain
{
    use AsObject;

    /**
     * @throws \App\Exceptions\IrisWebsiteNotFound
     */
    public function handle($domain): ?Website
    {

        $domain = $this->parseDomain($domain);

        /** @var Website $website */
        $website = Website::where('domain', $domain)->first();
        if (!$website) {
            throw IrisWebsiteNotFound::make();
        }

        return $website;
    }

    public function parseDomain(string $domain)
    {
        $domain = strtolower($domain);
        if (app()->environment('local')) {
            if ($domain == 'fulfilment.test') {
                $domain = config('app.local.retina_fulfilment_domain');
            } elseif ($domain == 'ds.test') {
                $domain = config('app.local.retina_dropshipping_domain');
            } else {
                $domain = config('app.local.retina_b2b_domain');
            }
            return $domain;
        }
        if ($domain == config('app.domain') ||  $domain == 'app.'.config('app.domain')) {
            return null;
        }

        if (app()->environment('staging')) {
            $domain = str_replace('canary.', '', $domain);
        }
        $domain = str_replace('www.', '', $domain);
        return  str_replace('v2.', '', $domain);



    }

}
