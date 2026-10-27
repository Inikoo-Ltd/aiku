<?php

/*
 * Created: Mon, 26 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Crawl\AuditWebsite;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * The page's HTML after its JavaScript has run, as Google renders it before reading structured data.
 */
class RenderWebpageHtml
{
    use AsObject;
    use AsFake;

    public function handle(string $url): ?string
    {
        try {
            $browsershot = Browsershot::url($url)
                ->userAgent(AuditWebsite::USER_AGENT)
                ->waitUntilNetworkIdle()
                ->timeout(30);

            if (!app()->isProduction()) {
                $browsershot->ignoreHttpsErrors();
            }

            return $browsershot->bodyHtml();
        } catch (Throwable) {
            return null;
        }
    }
}
