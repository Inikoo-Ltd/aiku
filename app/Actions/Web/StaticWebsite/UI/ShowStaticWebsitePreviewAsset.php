<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\Web\StaticWebsite\Iris\ServeStaticWebsite;
use App\Models\Web\StaticWebsite;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * The frozen files of an info website for its preview. Public on purpose: the preview page runs in a
 * sandbox with an opaque origin, so its requests carry no Aiku session, and these files are the same
 * ones the website itself serves to anyone.
 */
class ShowStaticWebsitePreviewAsset
{
    use AsAction;

    public function asController(string $staticWebsite, string $asset): Response
    {
        $website = StaticWebsite::where('slug', $staticWebsite)->first();
        abort_unless($website, 404);

        $assetPrefix = Str::beforeLast(route('static_website_preview_asset', [$website->slug, 'asset']), 'asset');

        return ServeStaticWebsite::make()->assetResponse($website, $asset, $assetPrefix);
    }
}
