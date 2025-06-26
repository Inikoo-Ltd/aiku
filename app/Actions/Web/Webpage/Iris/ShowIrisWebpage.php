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
        $webpage = Webpage::find($webpageID);
        if (!$webpage) {
            return [
                'status' => 'not_found',
            ];
        }


        $webPageLayout = $webpage->published_layout;


        $webBlocks = $this->getIrisWebBlocks(
            webpage: $webpage,
            webBlocks: Arr::get($webPageLayout, 'web_blocks', []),
            isLoggedIn: auth()->check()
        );


        return [
            'status'         => 'ok',
            'breadcrumbs'    => null,
            'meta'           => $webpage->seo_data,
            'script_website' => Arr::get($webpage->website->settings, 'script_website.header'),
            'web_blocks'     => $webBlocks,
        ];
    }


    public function handle(string $path, array $parentPaths, ActionRequest $request): array
    {
        if (config('iris.cache.webpage_path.ttl') == 0) {
            $webpageID = $this->getWebpageID($request->get('website'), $path);
        } else {
            $key       = config('iris.cache.webpage_path.prefix').'_'.$request->get('website')->id.'_'.$path;
            $webpageID = cache()->remember($key, config('iris.cache.webpage_path.ttl'), function () use ($request, $path) {
                return $this->getWebpageID($request->get('website'), $path);
            });
        }


        if ($webpageID === null) {
            abort(404, 'Not found');
        }


        if (config('iris.cache.webpage.ttl') == 0) {
            $webpageData = $this->getWebpageData($webpageID);
        } else {
            $key = config('iris.cache.webpage.prefix').'_'.$request->get('website')->id.'_'.(auth()->check() ? 'in' : 'out').'_'.$webpageID;

            $webpageData = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($webpageID) {
                return $this->getWebpageData($webpageID);
            });
        }

        if (Arr::get($webpageData, 'status') != 'ok') {
            abort(404, 'Not found');
        }

        return $webpageData;
    }


    public function asController(ActionRequest $request, string $path = null): array
    {
        return $this->handle($path, [], $request);
    }

    public function deep1(ActionRequest $request, string $parentPath1, string $path): array
    {
        return $this->handle($path, [$parentPath1], $request);
    }

    public function deep2(ActionRequest $request, string $parentPath1, string $parentPath2, string $path = null): array
    {
        return $this->handle($path, [$parentPath1, $parentPath2], $request);
    }

    public function deep3(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, string $path = null): array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3], $request);
    }

    public function deep4(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, string $parentPath4, string $path = null): array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3, $parentPath4], $request);
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
