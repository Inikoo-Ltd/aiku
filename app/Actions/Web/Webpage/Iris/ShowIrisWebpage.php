<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Web\Webpage\WithIrisGetWebpageWebBlocks;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowIrisWebpage
{
    use AsController;
    use WithIrisGetWebpageWebBlocks;


    public function getWebpageData($webpageID): array
    {
        $webpage       = Webpage::find($webpageID);
        $webPageLayout = $webpage->published_layout;


        $webBlocks = $this->getIrisWebBlocks(
            webpage: $webpage,
            webBlocks: Arr::get($webPageLayout, 'web_blocks', []),
            isLoggedIn: auth()->check()
        );


        return [
            'breadcrumbs' => null,  // TODO: same structure as Grp and Retina
            'meta'   => $webpage->seo_data,
            'script_website' => Arr::get($webpage->website->settings, 'script_website.header', null),
            'web_blocks' => $webBlocks,
        ];
    }


    public function asController(ActionRequest $request, string $path = null): array
    {
        if (config('iris.cache.webpage_path.ttl') == 0) {
            $webpageID = $this->getWebpageID($request->get('website'), $path);
        } else {
            $key = config('iris.cache.webpage_path.prefix').'_'.$request->get('website')->id.'_'.$path;
            $webpageID = cache()->remember($key, config('iris.cache.webpage_path.ttl'), function () use ($request, $path) {
                return $this->getWebpageID($request->get('website'), $path);
            });
        }


        if ($webpageID === null) {
            abort(404, 'Not found');
        }


        if (config('iris.cache.webpage.ttl') == 0) {
            return $this->getWebpageData($webpageID);
        }
        $key = config('iris.cache.webpage.prefix').'_'.$request->get('website')->id.'_'.(auth()->check() ? 'in' : 'out').'_'.$webpageID;

        return cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($webpageID) {
            return $this->getWebpageData($webpageID);
        });
    }

    public function htmlResponse($webpageData): Response
    {
        return Inertia::render(
            'IrisWebpage',
            $webpageData
        );
    }


    private function getWebpageID(Website $website, ?string $path): ?int
    {
        if ($path === null) {
            $webpageID = $website->storefront_id;
        } else {
            $webpageID = DB::table('webpages')->where('website_id', $website->id)->where('url', $path)->value('id');
        }


        return $webpageID;
    }


}
