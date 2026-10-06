<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebpageStructuredDataSource
{
    use AsAction;

    /**
     * What the public site receives for this webpage when a visitor is logged out, so the JSON-LD
     * it builds in the browser can be built the same way in aiku.
     *
     * @return array{webpage_data: array<string, mixed>, web_blocks: array<int, mixed>, breadcrumbs: array<int, mixed>, website_url: string, website_name: ?string, currency_code: ?string}|null
     */
    public function handle(Webpage $webpage): ?array
    {
        $webpageData = ShowIrisWebpage::make()->getWebpageData($webpage->id, $this->getParentPaths($webpage), false);

        if (Arr::get($webpageData, 'status') != 'ok') {
            return null;
        }

        $website = $webpage->website;

        return [
            'webpage_data'  => Arr::get($webpageData, 'webpage_data', []),
            'web_blocks'    => Arr::get($webpageData, 'web_blocks', []),
            'breadcrumbs'   => Arr::get($webpageData, 'breadcrumbs', []),
            'website_url'   => $website->storefront?->getCanonicalUrl() ?? $website->getUrl(),
            'website_name'  => $website->name,
            'currency_code' => $webpage->shop->currency?->code,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getParentPaths(Webpage $webpage): array
    {
        $canonicalPath = trim((string)parse_url($webpage->canonical_url ?? '', PHP_URL_PATH), '/');

        if ($canonicalPath === '') {
            return [];
        }

        return array_slice(explode('/', $canonicalPath), 0, -1);
    }
}
