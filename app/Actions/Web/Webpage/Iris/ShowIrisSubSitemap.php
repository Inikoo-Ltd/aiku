<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\Iris;

use App\Models\Web\Website;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowIrisSubSitemap
{
    use AsController;

    public function asController(ActionRequest $request, string $sitemapType): Response
    {

        /** @var Website $website */
        $website = $request->get('website');
        // dd($website);

        $validSitemapTypes = [
            'products',
            'departments',
            'sub_departments',
            'families',
            'contents',
            'blogs'
        ];

        if (!in_array($sitemapType, $validSitemapTypes)) {
            abort(404, "Sitemap type not found");
        }

        $filePath = "sitemaps/{$sitemapType}_{$website->id}.xml";

        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, "Sitemap not found for this website");
        }

        $sitemap = Storage::disk('local')->get($filePath);

        return response($sitemap, 200, [
            "Content-Type"  => "application/xml",
            "Cache-Control" => "public, max-age=3600",
        ]);
    }
}
