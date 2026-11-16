<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 11:11:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Traits\WithWebpageHydrators;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class ReopenWebpage extends OrgAction
{
    use WithActionUpdate;
    use WithWebpageHydrators;


    public function handle(Webpage $webpage): Webpage
    {
        $webpage->update(
            [
                'redirect_webpage_id' => null,
                'state'               => WebpageStateEnum::LIVE,
            ]
        );

        foreach (Redirect::where('from_webpage_id', $webpage->id)->get() as $redirect) {
            $redirect->forceDelete();
        }
        if ($redirect = Redirect::where('website_id', $webpage->website_id)->where('from_url', $webpage->canonical_url)->first()) {
            $redirect->forceDelete();
        }
        if ($redirect = Redirect::where('website_id', $webpage->website_id)->where('from_path', $webpage->url)->first()) {
            $redirect->forceDelete();
        }


        $this->dispatchWebpageHydratorsAndRefresh($webpage);
        BreakWebpageCache::run($webpage);
        Cache::forget(config('iris.cache.webpage_path.prefix').'_'.$webpage->website_id.'_'.strtolower($webpage->url));

        return $webpage;
    }


    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }

}
