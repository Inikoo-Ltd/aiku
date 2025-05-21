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


    public function handle(Webpage $webpage): array
    {
        $webPageLayout               = $webpage->published_layout;


        $webBlocks = $this->getIrisWebBlocks(
            webpage: $webpage,
            webBlocks:Arr::get($webPageLayout, 'web_blocks', []),
            isLoggedIn: auth()->check()
        );




        return [
            'meta'   => $webpage->seo_data,
            'web_blocks' => $webBlocks,
        ];
    }


    public function asController(ActionRequest $request, string $path = null): array
    {
        /** @var Website $website */
        $website = $request->get('website');
        $webpageID = $this->getWebpageID($website, $path);

        if ($webpageID === null) {
            abort(404);
        }

        $webpage   = Webpage::find($webpageID);



        return $this->handle($webpage);
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
