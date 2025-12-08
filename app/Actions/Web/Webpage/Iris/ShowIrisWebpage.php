<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Web\Webpage\WithIrisGetWebpageWebBlocks;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisWebpage
{
    use AsAction;
    use WithIrisGetWebpageWebBlocks;


    public function getCanonicalUrl($webpageID): ?string
    {
        $webpageData = DB::table('webpages')->select('canonical_url')->where('id', $webpageID)->first();

        return $webpageData?->canonical_url;
    }

    public function getWebpageData($webpageID, array $parentPaths, bool $loggedIn): array
    {
        $webpage = Webpage::find($webpageID);
        if (!$webpage) {
            return [
                'status' => 'not_found',
            ];
        }

        $webBlocks = $this->getIrisWebBlocks(
            webpage: $webpage,
            webBlocks: Arr::get($webpage->published_layout, 'web_blocks', []),
            isLoggedIn: $loggedIn
        );


        $webpageImg = [];
        if ($webpage->seoImage) {
            $webpageImg = $webpage->imageSources(1200, 1200, 'seoImage');
        }

        $baseWebpageData = [
            'breadcrumbs'  => $this->getIrisBreadcrumbs(
                webpage: $webpage,
                parentPaths: $parentPaths
            ),
            'webpage_data' => [
                'seo_data'      => $webpage->seo_data,
                'title'         => $webpage->title,
                'description'   => $webpage->description,
                'canonical_url' => $webpage->canonical_url,

            ],
            'webpage_img'  => $webpageImg,
        ];

        return array_merge($baseWebpageData, [
            'status'     => 'ok',
            'webpage_id' => $webpageID,
            'web_blocks' => $webBlocks,
        ]);
    }


    public function handle(?string $path, array $parentPaths, ActionRequest $request): string|array
    {
        $xLoggedStatus = $request->header('X-Logged-Status');
        if ($xLoggedStatus !== null) {
            $loggedIn = $xLoggedStatus === 'In';
        } else {
            $loggedIn = auth()->check();
        }

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
            $canonicalUrl = $this->getCanonicalUrl($webpageID);
        } else {
            $key = config('iris.cache.webpage.prefix').'_'.$request->get('website')->id.'_canonicals_'.$webpageID;

            $canonicalUrl = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($webpageID) {
                return $this->getCanonicalUrl($webpageID);
            });
        }


        if (!empty($canonicalUrl)) {
            // Use current URL without query parameters for canonical comparison
            $currentUrl = rtrim($request->url(), '/');

            // Normalize canonical URL to current environment and strip any query parameters
            $canonNoQuery    = explode('?', $canonicalUrl, 2)[0];
            $normalizedCanon = $this->getEnvironmentUrl(rtrim($canonNoQuery, '/'));

            if ($normalizedCanon !== $currentUrl) {
                return $this->getEnvironmentUrl($canonicalUrl);
            }
        }

        if (config('iris.cache.webpage.ttl') == 0) {
            $webpageData = $this->getWebpageData($webpageID, $parentPaths, $loggedIn);
        } else {
            $key         = config('iris.cache.webpage.prefix').'_'.$request->get('website')->id.'_'.($loggedIn ? 'in' : 'out').'_'.$webpageID;
            $webpageData = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($webpageID, $parentPaths, $loggedIn) {
                return $this->getWebpageData($webpageID, $parentPaths, $loggedIn);
            });
        }

        if (Arr::get($webpageData, 'status') != 'ok') {
            abort(404, 'Not found');
        }

        return $webpageData;
    }


    public function getEnvironmentUrl($url)
    {
        $environment = app()->environment();
        $website     = request()->website ?? null;

        if ($environment === 'local') {
            $shopType = $website?->shop?->type ?? null;

            $localDomain = match ($shopType) {
                ShopTypeEnum::FULFILMENT => 'fulfilment.test',
                ShopTypeEnum::DROPSHIPPING => 'ds.test',
                default => 'ecom.test',
            };

            return replaceUrlSubdomain(replaceUrlDomain($url, $localDomain), '');
        } elseif ($environment == 'staging') {
            return replaceUrlSubdomain($url, 'canary');
        }

        return $url;
    }

    public function asController(ActionRequest $request, string $path = null): string|array
    {
        return $this->handle($path, [], $request);
    }

    public function deep1(ActionRequest $request, string $parentPath1, string $path): string|array
    {
        return $this->handle($path, [$parentPath1], $request);
    }

    public function deep2(ActionRequest $request, string $parentPath1, string $parentPath2, string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2], $request);
    }

    public function deep3(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3], $request);
    }

    public function deep4(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, string $parentPath4, string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3, $parentPath4], $request);
    }


    public function htmlResponse($webpageData): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        if (is_string($webpageData)) {
            $queryParameters = Arr::except(request()->query(), [
                'favicons',
                'website',
                'domain',
                'currency_data',
                'shop_type'
            ]);
            $queryString     = http_build_query($queryParameters);

            if ($queryString) {
                $webpageData = $webpageData.'?'.$queryString;
            }

            return redirect()->to($webpageData, 301)
                ->withHeaders([
                    'x-original-referer' => request()->headers->get('referer', '')
                ]);
        }

        $browserTitle = Arr::get($webpageData, 'webpage_data.title', '');

        $response = Inertia::render(
            'IrisWebpage',
            $webpageData
        )->withViewData([
            'browserTitle' => $browserTitle,
        ])->toResponse(request());

        $response->header('X-AIKU-WEBSITE', (string)request()->website->id);
        if (isset($webpageData['webpage_id'])) {
            $response->header('X-AIKU-WEBPAGE', (string)$webpageData['webpage_id']);
        }

        return $response;
    }


    public function getWebpageID(Website $website, ?string $path): ?int
    {
        if ($path === null) {
            $webpageID = $website->storefront_id;
        } else {
            $webpageID = DB::table('webpages')->where('website_id', $website->id)
                ->where('url', strtolower($path))
                ->where('state', '=', WebpageStateEnum::LIVE)
                ->whereNull('deleted_at')
                ->value('id');
        }


        return $webpageID;
    }


    public function getPathWebpage(Webpage $webpage, string $parentPath): ?Webpage
    {
        $parentPathWebpageId = $this->getWebpageID($webpage->website, $parentPath);
        if ($parentPathWebpageId == $webpage->id) {
            return null;
        }
        $parentWebpage = Webpage::find($parentPathWebpageId);
        if ($parentWebpage) {
            return $parentWebpage;
        }

        return null;
    }

    public function getIrisBreadcrumbs(Webpage $webpage, array $parentPaths): array
    {
        $breadcrumbs[] = [
            'type'   => 'simple',
            'simple' => [
                'icon' => 'fal fa-home',
                'url'  => '/'
            ]
        ];


        foreach ($parentPaths as $parentPath) {
            /** @var Webpage $parentWebpage */
            $parentWebpage = $this->getPathWebpage($webpage, $parentPath);


            if ($parentWebpage && $parentWebpage->url) {
                $breadcrumbs[] =
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => $this->getBreadcrumbLabel($parentWebpage),
                            'url'   => $this->getEnvironmentUrl($parentWebpage->canonical_url)
                        ]

                    ];
            }
        }

        if ($webpage->url && $webpage->url != '/') {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'label' => $this->getBreadcrumbLabel($webpage),
                    'url'   => $this->getEnvironmentUrl($webpage->canonical_url)
                ]

            ];
        }

        if (count($breadcrumbs) == 1) {
            return [];
        }

        return $breadcrumbs;
    }

    public function getBreadcrumbLabel(Webpage $webpage): string
    {
        if ($webpage->model_type == 'Product') {
            /** @var Product $product */
            $product = $webpage->model;
            if ($product) {
                return $product->code;
            }
        }

        $label = $webpage->breadcrumb_label;


        if (!$label) {
            $label = $webpage->title;
        }
        if (!$label) {
            $label = $webpage->code;
        }

        return $label ?? '';
    }

}
