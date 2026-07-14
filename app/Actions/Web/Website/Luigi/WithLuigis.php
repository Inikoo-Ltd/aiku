<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:21:22 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Luigi;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

trait WithLuigis
{
    use AsAction;
    use WithAttributes;

    public function digest($key, $content_type, $method, $endpoint, $date): string
    {
        $data = "$method\n$content_type\n$date\n$endpoint";

        return trim(base64_encode(hash_hmac('sha256', $data, $key, true)));
    }

    public function getAccessToken(Website $website): array
    {
        if (app()->environment('production')) {
            return array_filter([Arr::get($website->settings, 'luigisbox.tracker_id'), Arr::get($website->settings, 'luigisbox.private_key')]);
        } else {
            return array_filter([config('app.sandbox.luigisbox.tracker_id'), config('app.sandbox.luigisbox.private_key')]);
        }
    }

    /**
     * @throws \Exception
     */
    private function request(Website|Webpage $parent, string $endPoint, array $body, string $method = 'post', $compressed = false, $queryParams = null): array
    {
        $content_type = 'application/json; charset=utf-8';

        $offsetSeconds = 0;
        $date          = gmdate('D, d M Y H:i:s', time() + $offsetSeconds).' GMT';


        if ($parent instanceof Website) {
            $website = $parent;
        } else {
            if ($parent->model_type == 'Product') {
                Log::info('Product Code: '.$parent->slug);
            }
            $website = $parent->website;
        }

        if (!$website->migrated) {
            abort(404, 'Website not migrated');
        }

        $accessToken = $this->getAccessToken($website);

        [$publicKey, $privateKey] = $accessToken;

        if (strtoupper($method) === 'GET') {
            $bodyToSend = '';
        } else {
            $bodyJson = json_encode($body);

            if ($compressed) {
                $header['Content-Encoding'] = 'gzip';
                $bodyToSend                 = gzencode($bodyJson, 9);
            } else {
                $bodyToSend = $bodyJson;
            }
        }

        $signature = $this->digest(
            $privateKey,
            $content_type,
            strtoupper($method),
            $endPoint,
            $date
        );

        if ($queryParams) {
            $endPoint .= '?'.$queryParams;
        }

        $header = [
            'Accept-Encoding' => 'gzip',
            'Content-Type'    => $content_type,
            'Date'            => $date,
            'Authorization'   => "Hello $publicKey:$signature",
        ];

        Log::info('compressed: '.$compressed);
        Log::info('Starting request to Luigi Box API '.$publicKey.' ('.$date.')...');
        Log::info('Headers: ', $header);
        Log::info('Body: ', ['body' => $bodyToSend]);
        Log::info('Loading...');

        try {
            $response = Http::withHeaders($header)
                ->retry(3, 100);

            if (!empty($bodyToSend)) {
                $response = $response->withBody($bodyToSend, $content_type);
            }


            $response = $response->{strtolower($method)}(
                'https://live.luigisbox.tech'.$endPoint
            );
        } catch (\Exception $e) {
            throw new Exception('Failed to call Luigis Box API: '.$e->getMessage());
        }

        if ($response->failed()) {
            Log::error('Failed to send request to Luigis Box API: '.$response->body(), [
                'ResponseBody' => $response->body(),
            ]);
            throw new Exception('Failed to send request to Luigis Box API: '.$response->body());
        } else {
            Log::info('Successfully sent request to Luigis Box API', [
                'ResponseBody' => $response->body(),
            ]);

            return json_decode($response->body(), true);
        }
    }


    public function getWebpageUrl(Webpage $webpage): string
    {
        $model = $webpage->model;

        // Start with just the current webpage's URL
        $segments = [];

        if ($model instanceof Product) {
            $family     = $model->family;
            $department = $family?->department;

            $segments = collect([
                optional($department->webpage ?? null)->url ?? null,
                optional($family->webpage ?? null)->url ?? null,
                $webpage->url,
            ])->filter()->all();
        } elseif ($model instanceof ProductCategory) {
            if ($model->type === ProductCategoryTypeEnum::DEPARTMENT) {
                $segments = [
                    optional($model->webpage ?? null)->url,
                ];
            } elseif ($model->type === ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $segments = collect([
                    optional($model->department?->webpage ?? null)->url,
                    $webpage->url,
                ])->filter()->all();
            } elseif ($model->type === ProductCategoryTypeEnum::FAMILY) {
                $segments = collect([
                    optional($model->department?->webpage ?? null)->url,
                    optional($model->subDepartment?->webpage ?? null)->url,
                    $webpage->url,
                ])->filter()->all();
            }
        } else {
            $segments = [$webpage->url];
        }

        return '/'.collect($segments)->implode('/');
    }


    public function reindexTags(Webpage|Website $parent, LaravelCollection $tags): void
    {
        $objects = [];
        foreach ($tags as $tag) {
            $url       = '/search?lb.t[]=tag:'.$tag->name.'&q='.$tag->name;
            $objects[] = [
                "identity" => $url,
                "type"     => "tag",
                "fields"   => array_filter([
                    "slug"       => 'tag-'.$tag->slug,
                    "title"      => $tag->name,
                    "web_url"    => $url,
                    "image_link" => Arr::get($tag->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        if ($objects) {
            $body = [
                'objects' => $objects
            ];

            try {
                $this->request($parent, '/v1/content', $body);
            } catch (Exception $e) {
                //
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function reindexBrands(Webpage|Website $parent, LaravelCollection $brands): void
    {
        $objects = [];
        foreach ($brands as $brand) {
            $url       = '/search?lb.f[]=brand:'.$brand->name.'&q='.$brand->name;
            $objects[] = [
                "identity" => $url,
                "type"     => "brand",
                "fields"   => array_filter([
                    "slug"       => 'brand-'.$brand->slug,
                    "title"      => $brand->name,
                    "web_url"    => $url,
                    "image_link" => Arr::get($brand->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        if ($objects) {
            $body = [
                'objects' => $objects
            ];

            try {
                $this->request($parent, '/v1/content', $body);
            } catch (Exception $e) {
                //
            }
        }
    }

    public function deleteContentFromWebsite(Website $website): void
    {
        $website->webpages()
            ->where('state', 'live')
            ->whereIn('type', [WebpageTypeEnum::CATALOGUE])
            ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
            ->chunk(1000, function ($webpages) use ($website) {
                $batch = [];
                foreach ($webpages as $webpage) {
                    $object = $this->getObjectFromWebpage($webpage);
                    if ($object) {
                        $batch[] = $object;
                    }
                }
                if ($batch) {
                    $compressed = count($batch) >= 1000;
                    $body       = [
                        'objects' => $batch
                    ];

                    try {
                        $this->request($website, '/v1/content/delete', $body, 'delete', $compressed);
                    } catch (Exception $e) {
                        //
                    }

                    print "Deleted count ".count($batch)." from website: $website->name\n";
                }
            });
    }

    /**
     * @throws \Exception
     */
    public function deleteContentFromWebpage(Webpage $webpage): void
    {
        $website = $webpage->website;
        if ($webpage->model instanceof Product) {
            $objects = [
                [
                    "type"     => "item",
                    "identity" => $webpage->luigiIdentity(),
                ],
            ];

            $body = [
                'objects' => $objects
            ];
        } else {
            $body = [
                'objects' => [
                    [
                        "type"     => 'category',
                        "identity" => $webpage->url,
                        //todo: "identity" => $this->getIdentity($webpage)
                    ]
                ]
            ];
        }
        try {
            $this->request($website, '/v1/content', $body, 'delete');
        } catch (Exception $e) {
            //
        }
    }

    /**
     * @throws \Exception
     */
    public function deleteContentFromLuigi(Website $website, string $identity, string $type): void
    {
        $body = [
            'objects' => [
                [
                    "type"     => $type,
                    "identity" => $identity,
                ]
            ]
        ];
        try {
            $this->request($website, '/v1/content/delete', $body, 'delete');
        } catch (Exception $e) {
            //
        }
    }

    /**
     * @throws \Exception
     */
    public function deleteContentManual(Website $website, array $object): void
    {
        try {
            $this->request(
                $website,
                '/v1/content/delete',
                [
                    'objects' => [
                        $object
                    ]
                ],
                'delete'
            );
        } catch (Exception $e) {
            //
        }
    }

    public function getIdentity(Webpage $webpage): string
    {
        return "webpage-$webpage->slug";
    }

    public function getIdentityTag(Tag $tag): string
    {
        return "tag-$tag->slug";
    }

    public function getIdentityBrand(Brand $brand): ?string
    {
        return "brand-$brand->slug";
    }

    public function getProductObjectFromWebpage(Product $product): array
    {
        $webpage = $product->webpage;

        $familyData = [];
        if ($product->family && $product->family->webpage && $product->family->webpage->state == WebpageStateEnum::LIVE) {
            $family     = $product->family;
            $familyData = [
                "type"     => "category",
                "identity" => $family->webpage->luigiIdentity(),
                "fields"   => array_filter([
                    "slug"        => 'webpage-'.$webpage->slug,
                    "title"       => $family->webpage->title,
                    "web_url"     => $family->webpage->getCanonicalUrl(),
                    "description" => $family->webpage->description,
                    "image_link"  => Arr::get($family->imageSources(200, 200), 'original'),
                ])
            ];
        }


        $departmentData = [];
        if ($product->department && $product->department->webpage && $product->department->webpage->state == WebpageStateEnum::LIVE) {
            $department     = $product->department;
            $departmentData = [
                "type"     => "department",
                "identity" => $department->webpage->luigiIdentity(),
                "fields"   => array_filter([
                    "slug"        => 'webpage-'.$webpage->slug,
                    "title"       => $department->webpage->title,
                    "web_url"     => $department->webpage->getCanonicalUrl(),
                    "description" => $department->webpage->description,
                    "image_link"  => Arr::get($department->imageSources(200, 200), 'original'),
                ]),
            ];
        }


        $subDepartmentData = [];
        if ($product->subDepartment && $product->subDepartment->webpage && $product->subDepartment->webpage->state == WebpageStateEnum::LIVE) {
            $subDepartment     = $product->subDepartment;
            $subDepartmentData = [
                "type"     => "sub_department",
                "identity" => $subDepartment->webpage->luigiIdentity(),
                "fields"   => array_filter([
                    "slug"        => 'webpage-'.$subDepartment->webpage->slug,
                    "title"       => $subDepartment->webpage->title,
                    "web_url"     => $subDepartment->webpage->getCanonicalUrl(),
                    "description" => $subDepartment?->webpage->description,
                    "image_link"  => Arr::get($subDepartment->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        $brand       = $product->getBrand();
        $brandObject = [];
        if ($brand) {
            $url         = '/search?lb.f[]=brand:'.$brand->name.'&q='.$brand->name;
            $brandObject = [
                "identity" => $url,
                "type"     => "brand",
                "fields"   => array_filter([
                    "slug"       => 'brand-'.$brand->slug,
                    "title"      => $brand->name,
                    "web_url"    => $url,
                    "image_link" => Arr::get($brand->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        $tags       = $product->tradeUnitTagsViaTradeUnits();
        $tagsObject = [];
        if ($tags->isNotEmpty()) {
            foreach ($tags as $tag) {
                $url          = '/search?lb.t[]=tag:'.$tag->name.'&q='.$tag->name;
                $tagsObject[] = [
                    "identity" => $url,
                    "type"     => "tag",
                    "fields"   => array_filter([
                        "slug"       => 'tag-'.$tag->slug,
                        "title"      => $tag->name,
                        "web_url"    => $url,
                        "image_link" => Arr::get($tag->imageSources(200, 200), 'original'),
                    ]),
                ];
            }
        }


        $productUnits = (float) $product->units;
        $price = (float) ($product->price ?? 0);
        $rrp   = (float) ($product->rrp ?? 0);
        $pricePerUnit = $productUnits > 0 ? $price / $productUnits : 0;
        $rrpPerUnit   = $productUnits > 0 ? $rrp / $productUnits : 0;

        return [
            "identity" => $webpage->luigiIdentity(),
            "type"     => "item",
            "fields"   => array_filter([
                "slug"                => 'webpage-'.$webpage->slug,
                "title"               => $webpage->title,
                "web_url"             => $webpage->getCanonicalUrl(),
                // Discontinuing display also (Tomas Request) | HELP-1677
                "availability"        => intval(($product->state == ProductStateEnum::ACTIVE || $product->state == ProductStateEnum::DISCONTINUING) && $product->has_live_webpage && $product->is_main && $product->is_for_sale),
                "stock_qty"           => $product->available_quantity ?? 0,
                "unit"                => $product->unit,   // 'bomb'
                "units"               => $productUnits,   // '6.000'

                "price"                             => $price,
                "formatted_price"                   => $product->currency->symbol.$price.'/'.$product->unit,
                "price_rrp"                         => $rrp,
                "formatted_price_rrp"               => $product->currency->symbol.$rrp.'/'.$product->unit,
                "price_per_unit"                    => $pricePerUnit,
                "formatted_price_per_unit"          => $product->currency->symbol . number_format($pricePerUnit, 2) . '/'. $product->unit,
                "price_rrp_per_unit"                => $rrpPerUnit,
                "formatted_price_rrp_per_unit"      => $product->currency->symbol . number_format($rrpPerUnit, 2) . '/'. $product->unit,

                "image_link"          => Arr::get($product->imageSources(200, 200), 'original'),
                "product_code"        => $product->code,
                "product_id"          => $product->id,
                "introduced_at"       => $product->created_at ? $product->created_at->format('c') : null,
                "description"         => $product->description,
                'website_id'          => $webpage->website_id,
                'webpage_id'          => $webpage->id,
                'reindex_at'          => now()->utc()->format('c'),
            ]),
            ...(count($familyData) || count($departmentData) || count($subDepartmentData) || count($brandObject) || count($tagsObject) ? [
                "nested" => array_values(array_filter([
                    ...$tagsObject,
                    $brandObject,
                    $familyData,
                    $subDepartmentData,
                    $departmentData
                ])),
            ] : []),

        ];
    }

    public function getObjectFromWebpage(Webpage $webpage): array
    {
        /** @var Product|Collection|ProductCategory $model */
        $model = $webpage->model;

        if ($model instanceof Product) {
            return $this->getProductObjectFromWebpage($model);
        } else {
            $modelWebpage = $model?->webpage;
            $type         = null;
            if (!$modelWebpage) {
                if ($webpage->type == WebpageTypeEnum::BLOG) {
                    $type = 'news';
                } else {
                    return [];
                }
            }

            return [
                "identity" => $webpage->luigiIdentity(),
                "type"     => $type ?? $this->getType($model),
                "fields"   => array_filter([
                    "slug"        => $this->getIdentity($modelWebpage),
                    "title"       => $modelWebpage->title,
                    "web_url"     => $modelWebpage->getCanonicalUrl(),
                    "description" => $modelWebpage->description,
                    "image_link"  => Arr::get($model->imageSources(200, 200), 'original'),
                ]),
            ];
        }
    }

    private function getType(ProductCategory|Collection|Product|Brand $model): string
    {
        if ($model instanceof Product) {
            return 'item';
        }
        if ($model instanceof Brand) {
            return 'brand';
        }

        if ($model instanceof Collection) {
            return 'collection';
        }

        return match ($model->type) {
            ProductCategoryTypeEnum::DEPARTMENT => 'department',
            ProductCategoryTypeEnum::SUB_DEPARTMENT => 'sub_department',
            default => 'category',
        };
    }

    public function getContentExport(Website $website, $queryParams = 'size=500'): array
    {
        return $this->request($website, '/v1/content_export', [], 'GET', false, $queryParams);
    }

    public function getNextPagination(array $urlArr): string|null
    {
        return data_get(array_find($urlArr, function ($item) {
            return data_get($item, 'rel', null) == 'next';
        }), 'href', null);
    }
}
