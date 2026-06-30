<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Catalogue\Review\UI\IndexReviewsInIris;
use App\Actions\Web\Webpage\WithIrisGetWebpageWebBlocks;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
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

        $website = $webpage->website;

        $title = $webpage->title;
        // Prioritize webpage prefix/suffix -> website prefix/suffix
        $prefix = data_get($webpage->settings, 'webpage.title_prefix', data_get($website->settings, 'webpage.title_prefix', null));
        $suffix = data_get($webpage->settings, 'webpage.title_suffix', data_get($website->settings, 'webpage.title_suffix', null));

        $title = collect([$prefix, $title, $suffix])->filter()->implode(' ');
        $reviews = [];
        $avgReview = 0;
        
        if ($webpage->model instanceof ProductCategory && $webpage->sub_type == ProductCategoryTypeEnum::FAMILY->value) {
            $reviews = IndexReviewsInIris::run(parent: $webpage->model, prefix: $webpage->title);
            $avgReview = IndexReviewsInIris::make()->avgReview($webpage->model);
        } elseif (!($webpage->model instanceof Product)) {
            $reviews = IndexReviewsInIris::run(parent: $webpage->shop, prefix: $webpage->title);
            $avgReview = IndexReviewsInIris::make()->avgReview($webpage->shop);
        }

        $baseWebpageData = [
            'breadcrumbs'                 => $this->getIrisBreadcrumbs(
                webpage: $webpage,
                parentPaths: $parentPaths
            ),
            'navigation'                  => $this->getIrisProductNavigation($webpage),
            'webpage_data'                => [
                'seo_data'      => $webpage->seo_data,
                'title'         => $title,
                'description'   => $webpage->description,
                'canonical_url' => $webpage->canonical_url,
                'type'          => $webpage->type,
                'sub_type'      => $webpage->sub_type,  // 'sub_department', 'department', 'product', 'category'
                'model_type'    => $webpage->model_type,  // Product, ProductCategory, etc
                'product_page'  => $webpage->model instanceof Product
                    ? ['department' => [
                        'name'          => $webpage->model->department?->name,
                        'webpage_title' => $webpage->model->department?->webpage?->title,
                    ]]
                    : null,
            ],
            'webpage_img'                       => $webpageImg,
            'index_page'                        => $webpage->index_page,
            'follow_link'                       => $webpage->follow_link,
            'webpage_slug'                      => $webpage->slug,
            'reviews'                           => ReviewsInIrisResource::collection($reviews),
            'review_summary'                    => $avgReview ?? 0,
            'allow_review_reaction'             => Arr::get($webpage->shop->settings, 'reviews.allow_reactions', true),
            'allow_review_reply_reaction'       => Arr::get($webpage->shop->settings, 'reviews.allow_reactions', true),
            'minimum_reviews_to_show'           => Arr::get($webpage->shop->settings, 'reviews.minimum_reviews_to_show', 0),
            'is_different_when_logged_in'       => $webpage->is_different_when_logged_in,
            'webpage_slug'                      => $webpage->slug
        ];

        return array_merge($baseWebpageData, [
            'status'     => 'ok',
            'webpage_id' => $webpageID,
            'web_blocks' => $webBlocks,
        ]);
    }


    public function handle(?string $path, array $parentPaths, ActionRequest $request): string|array
    {
        if ($path == 'robots.txt') {
            return 'robots';
        }


        $loggedStatusFromHeader = $request->header('X-Logged-Status');
        if ($loggedStatusFromHeader !== null) {
            $loggedIn = $loggedStatusFromHeader === 'In';
        } else {
            $loggedIn = auth()->check();
        }

        if (config('iris.cache.webpage_path.ttl') == 0) {
            $webpageID = $this->getWebpageID($request->input('website'), $path);
        } else {
            $key       = config('iris.cache.webpage_path.prefix').'_'.$request->input('website')->id.'_'.$path;
            $webpageID = cache()->remember($key, config('iris.cache.webpage_path.ttl'), function () use ($request, $path) {
                return $this->getWebpageID($request->input('website'), $path);
            });
        }


        if ($webpageID === null) {
            abort(404, 'Not found');
        }


        if (config('iris.cache.webpage.ttl') == 0) {
            $canonicalUrl = $this->getCanonicalUrl($webpageID);
        } else {
            $key = config('iris.cache.webpage.prefix').'_'.$request->input('website')->id.'_canonicals_'.$webpageID;

            $canonicalUrl = cache()->remember($key, config('iris.cache.webpage.ttl'), function () use ($webpageID) {
                return $this->getCanonicalUrl($webpageID);
            });
        }


        if (!empty($canonicalUrl)) {
            // Use current URL without query parameters for canonical comparison
            $currentUrl = rtrim($request->url(), '/');


            $normalizedCanon = $this->getEnvironmentUrl($canonicalUrl);

            if ($normalizedCanon !== $currentUrl) {
                return $this->getEnvironmentUrl($canonicalUrl);
            }
        }

        if (config('iris.cache.webpage.ttl') == 0) {
            $webpageData = $this->getWebpageData($webpageID, $parentPaths, $loggedIn);
        } else {
            $key         = config('iris.cache.webpage.prefix').'_'.$request->input('website')->id.'_'.($loggedIn ? 'in' : 'out').'_'.$webpageID;
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
        if ($environment == 'production') {
            return $url;
        }
        if ($environment === 'local') {
            $website  = request()->website ?? null;
            $shopType = $website?->shop?->type ?? null;

            $localDomain = match ($shopType) {
                ShopTypeEnum::FULFILMENT => 'fulfilment.test',
                ShopTypeEnum::DROPSHIPPING => 'ds.test',
                default => 'ecom.test',
            };

            return replaceUrlSubdomain(replaceUrlDomain($url, $localDomain), '');
        }
        if ($environment == 'staging') {
            return replaceUrlSubdomain($url, 'canary');
        }

        return $url;
    }

    public function asController(ActionRequest $request, ?string $path = null): string|array
    {
        return $this->handle($path, [], $request);
    }

    public function deep1(ActionRequest $request, string $parentPath1, ?string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1], $request);
    }

    public function deep2(ActionRequest $request, string $parentPath1, string $parentPath2, ?string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2], $request);
    }

    public function deep3(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, ?string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3], $request);
    }

    public function deep4(ActionRequest $request, string $parentPath1, string $parentPath2, string $parentPath3, string $parentPath4, ?string $path = null): string|array
    {
        return $this->handle($path, [$parentPath1, $parentPath2, $parentPath3, $parentPath4], $request);
    }


    public function htmlResponse($webpageData): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response|string
    {
        if (is_string($webpageData)) {

            if ($webpageData == 'robots') {
                $robotText = ShowIrisRobotsTxt::make()->getRobotText(request()->website);
                if (!$robotText) {
                    $robotText = 'User-agent: *';
                }

                return response($robotText, 200, [
                    'Content-Type'  => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }


            $queryParameters = Arr::except(request()->query(), [
                'favicons',
                'website',
                'domain',
                'currency_data',
                'shop_type',
                'locale'
            ]);

            $cacheRedirectInVarnish = '1';
            $queryString     = http_build_query($queryParameters);

            if ($queryString) {
                $webpageData = $webpageData.'?'.$queryString;
                $cacheRedirectInVarnish = '0';
            }

            if (request()->url() == $webpageData) {
                $cacheRedirectInVarnish = '0';
            }


            return redirect()->to($webpageData, 301)
                ->withHeaders([
                    'Cache-Control'             => 'public, s-maxage=300, max-age=0',
                    'X-Aiku-Cacheable-Redirect' => $cacheRedirectInVarnish,
                ]);
        }

        $browserTitle            = Arr::get($webpageData, 'webpage_data.title', '');
        $isDifferentWhenLoggedIn = Arr::pull($webpageData, 'is_different_when_logged_in');

        $response = Inertia::render(
            'IrisWebpage',
            $webpageData
        )->withViewData([
            'browserTitle' => $browserTitle,
        ])->toResponse(request());

        $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=0');
        $response->headers->set('X-Aiku-Cacheable-Inertia', '1');
        $response->headers->set('X-Is-Diff', $isDifferentWhenLoggedIn ? 'Y' : 'N');

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
            $webpage = Webpage::where('website_id', $website->id)
                ->where('url', strtolower($path))
                ->whereNull('deleted_at')
                ->first();

            if ($webpage?->state === WebpageStateEnum::LIVE) {
                $webpageID = $webpage->id;
            } else {
                $webpageID = DB::table('redirects')
                    ->select('to_webpage_id')
                    ->where('from_path', $path)
                    ->where('website_id', $website->id)
                    ->first()?->to_webpage_id;
            }
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
                            'short_label' => $this->getBreadcrumbShortLabel($parentWebpage),
                            'label'       => $this->getBreadcrumbLabel($parentWebpage),
                            'url'         => $this->getEnvironmentUrl($parentWebpage->canonical_url)
                        ]

                    ];
            }
        }

        if ($webpage->url && $webpage->url != '/') {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'short_label' => $this->getBreadcrumbShortLabel($webpage),
                    'label'       => $this->getBreadcrumbLabel($webpage),
                    'url'         => $this->getEnvironmentUrl($webpage->canonical_url)
                ]

            ];
        }

        if (count($breadcrumbs) == 1) {
            return [];
        }

        return $breadcrumbs;
    }

    public function getIrisProductNavigation(Webpage $webpage): ?array
    {
        if (!$webpage->model instanceof Product) {
            return null;
        }

        /** @var Product $product */
        $product = $webpage->model;
        if (!$product->family_id) {
            return null;
        }

        $siblings = Product::query()
            ->where('products.family_id', $product->family_id)
            ->where(function ($query) {
                $query->whereNull('products.variant_id')
                    ->orWhere('products.is_variant_leader', true);
            })
            ->whereHas('webpage', function ($query) use ($webpage) {
                $query->where('state', WebpageStateEnum::LIVE)
                    ->where('website_id', $webpage->website_id);
            })
            ->with(['webpage' => function ($query) use ($webpage) {
                $query->where('state', WebpageStateEnum::LIVE)
                    ->where('website_id', $webpage->website_id);
            }])
            ->orderBy('index_under_family')
            ->orderBy('code')
            ->get();

        $currentIndex = $siblings->search(fn (Product $sibling) => $sibling->id === $product->id);
        if ($currentIndex === false) {
            return null;
        }

        $navigation = [
            'previous' => $this->getProductNavigationItem($siblings->get($currentIndex - 1)),
            'next'     => $this->getProductNavigationItem($siblings->get($currentIndex + 1)),
        ];

        if (!$navigation['previous'] && !$navigation['next']) {
            return null;
        }

        return $navigation;
    }

    private function getProductNavigationItem(?Product $product): ?array
    {
        if (!$product || !$product->webpage) {
            return null;
        }

        return [
            'label' => $product->name,
            'url'   => $this->getEnvironmentUrl($product->webpage->canonical_url),
        ];
    }

    public function getBreadcrumbShortLabel(Webpage $webpage): string
    {
        if ($webpage->model_type == 'Product') {
            /** @var Product $product */
            $product = $webpage->model;
            if ($product) {
                return $product->code;
            }
        } elseif ($webpage->model_type == 'ProductCategory') {
            /** @var ProductCategory $productCategory */
            $productCategory = $webpage->model;
            if ($productCategory) {
                return $productCategory->code;
            }
        }

        $label = $webpage->code;

        return $label ?? '';
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
            $label = $webpage->title ?? $webpage->code;
        }

        return $label ?? '';
    }

}
