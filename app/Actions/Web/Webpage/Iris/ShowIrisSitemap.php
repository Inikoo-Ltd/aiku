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

class ShowIrisSitemap
{
    use AsController;

    public function asController(ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->get('website');
        $filePath = 'sitemaps/sitemap-' . $website->id . '.xml';

        if (!Storage::disk('local')->exists($filePath)) {
            abort(404);
        }

        $sitemap = Storage::disk('local')->get($filePath);

        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
