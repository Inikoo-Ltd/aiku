<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class BreakWebsiteCache extends OrgAction
{
    public function handle(Website $website): Website
    {
        $key = config('iris.cache.website.prefix')."_$website->domain";
        Cache::forget($key);

        ClearCacheByWildcard::run( config('iris.cache.webpage_path.prefix').'_'.$website->id.'_*');
        ClearCacheByWildcard::run( config('iris.cache.webpage.prefix').'_'.$website->id.'_*');

        return $website;
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

}
