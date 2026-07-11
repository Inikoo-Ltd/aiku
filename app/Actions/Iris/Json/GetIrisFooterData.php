<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 11:58:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use Throwable;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisFooterData
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var Website|null $website */
        $website = $request->input('website');

        if (!$website) {
            return response()->json([
                'footer' => []
            ]);
        }

        $cacheKey = "irisData:website:$website->id:footer";
        $ttl      = config('iris.cache.iris_website_data_ttl');


        $compute = function () use ($website) {
            $footerLayout   = Arr::get($website->published_layout, 'footer');
            $isFooterActive = Arr::get($footerLayout, 'status');

            return [
                'footer' => $this->refreshGrpAssetUrls(
                    $isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : [],
                ),
            ];
        };

        try {
            $data = Cache::remember($cacheKey, $ttl, $compute);
        } catch (Throwable) {
            $data = $compute();
        }

        return response()->json($data);
    }

    /**
     * Footer layouts store fully hashed /grp/assets urls from the build that was live
     * when the footer was saved; remap them to the current build's files.
     */
    private function refreshGrpAssetUrls(array $footer): array
    {
        $json = preg_replace_callback(
            '#/grp/assets/([A-Za-z0-9_.-]+)-[A-Za-z0-9_-]{8}\.(png|svg|jpe?g|webp|gif)#',
            function (array $matches) {
                $entry = $this->grpManifest()["resources/art/payment_service_providers/$matches[1].$matches[2]"] ?? [];
                $file  = $entry['file'] ?? null;

                return $file ? "/grp/$file" : $matches[0];
            },
            json_encode($footer, JSON_UNESCAPED_SLASHES)
        );

        return json_decode($json, true) ?? $footer;
    }

    private function grpManifest(): array
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifest = rescue(
                fn () => json_decode(file_get_contents(public_path('grp/manifest.json')), true) ?? [],
                [],
                false
            );
        }

        return $manifest;
    }
}
