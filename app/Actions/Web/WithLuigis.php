<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Brand;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
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

    public function getAccessToken(Website|Webpage $parent): array
    {
        $website = $parent instanceof Website ? $parent : $parent->website;
        if (app()->environment('production')) {
            return array_filter([Arr::get($website->settings, 'luigisbox.tracker_id'), Arr::get($website->settings, 'luigisbox.private_key')]);
        } else {
            return [config('app.sandbox.luigisbox.tracker_id'), config('app.sandbox.luigisbox.private_key')];
        }
    }

    /**
     * @throws \Exception
     */
    private function request(Website|Webpage $parent, string $endPoint, array $body, string $method = 'post', $compressed = false)
    {
        $content_type = 'application/json; charset=utf-8';

        $offsetSeconds = 0;
        $date          = gmdate('D, d M Y H:i:s', time() + $offsetSeconds).' GMT';

        [$publicKey, $privateKey] = $this->getAccessToken($parent);

        $signature  = $this->digest(
            $privateKey,
            $content_type,
            strtoupper($method),
            $endPoint,
            $date
        );

        $header = [
            'Accept-Encoding' => 'gzip',
            'Content-Type'    => $content_type,
            'Date'            => $date,
            'Authorization'  => "Hello {$publicKey}:{$signature}",
        ];

        if ($compressed) {
            $header['Content-Encoding'] = 'gzip';
            $body = gzencode(json_encode($body), 9);
        } else {
            $body = json_encode($body);
        }

        $response = Http::withHeaders($header)
            ->withBody($body, $content_type)
            ->{strtolower($method)}('https://live.luigisbox.com/'.$endPoint);


        if ($response->failed()) {
            throw new Exception('Failed to send request to Luigi\'s Box API: '.$response->body());
        }

        if ($response->successful()) {
            return $response->json();
        }
    }

    /**
     * @throws \Exception
     */
    public function reindex(Website|Webpage $parent): void
    {
        if ($parent instanceof Website) {
            $website = $parent;
            $tags = LaravelCollection::make();
            $website->webpages()
            ->with('model')
            ->where('state', 'live')
            ->whereIn('type', [WebpageTypeEnum::CATALOGUE, WebpageTypeEnum::BLOG])
            ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
            ->chunk(1000, function ($webpages) use ($website, &$tags) {
                $objects = [];
                foreach ($webpages as $webpage) {
                    $model = $webpage->model;
                    if ($model instanceof Product) {
                        // reindex tags not in nested item because nested get limit 10
                        $tagModel = $model->tradeUnitTagsViaTradeUnits();
                        if ($tagModel->isNotEmpty()) {
                            $tags = $tags->merge($tagModel);
                        }
                    }

                    $object = $this->getObjectFromWebpage($webpage);
                    if ($object) {
                        $objects[] = $object;
                    }

                }

                $body = [
                    'objects' => $objects
                ];
                $compressed = count($objects) >= 1000;
                $this->request($website, '/v1/content', $body, 'post', $compressed);
            });

            if ($tags->isNotEmpty()) {
                $this->reindexTags($website, $tags->unique('slug'));
                print "Reindexed " . $tags->count() . " tags for website: {$website->name}\n";
            }

        } else {
            $webpage = $parent;
            if ($webpage->type != WebpageTypeEnum::CATALOGUE && $webpage->type != WebpageTypeEnum::BLOG) {
                return;
            }

            $objects[] = $this->getObjectFromWebpage($webpage);

            $body = [
                'objects' => $objects
            ];
            $this->request($parent, '/v1/content', $body, 'post');
            print "Reindexed webpage: {$webpage->title} ({$webpage->url})\n";

            // reindex tags not in nested item because nested get limit 10
            $model = $webpage->model;
            if ($model instanceof Product) {
                $tags = $model->tradeUnitTagsViaTradeUnits();
                if ($tags->isNotEmpty()) {
                    $this->reindexTags($webpage, $tags->unique('slug'));
                    print "Reindexed " . $tags->count() . " tags for webpage: {$webpage->title} ({$webpage->url})\n";
                }
            }

        }
    }

    public function getWebpageUrl(Webpage $webpage): string
    {
        $model = $webpage->model;

        // Start with just the current webpage's URL
        $segments = [];

        if ($model instanceof Product) {
            $family = $model->family;
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

        return '/' . collect($segments)->implode('/');
    }


    public function reindexTags(Webpage|Website $parent, LaravelCollection $tags): void
    {
        $objects = [];
        foreach ($tags as $tag) {
            $url = '/search?lb.t[]=tag:' . $tag->name . '&q=' . $tag->name;
            $objects[] = [
                "identity" => $url,
                "type" => "tag",
                "fields" => array_filter([
                    "title" => $tag->name,
                    "web_url" => $url,
                    "image_link" => Arr::get($tag->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        if ($objects) {
            $body = [
                'objects' => $objects
            ];
            $this->request($parent, '/v1/content', $body, 'post');
        }
    }

    public function reindexBrands(Webpage|Website $parent, LaravelCollection $brands): void
    {
        $objects = [];
        foreach ($brands as $brand) {
            $url = '/search?lb.f[]=brand:' . $brand->name . '&q=' . $brand->name;
            $objects[] = [
                "identity" => $url,
                "type" => "brand",
                "fields" => array_filter([
                    "title" => $brand->name,
                    "web_url" => $url,
                    "image_link" => Arr::get($brand->imageSources(200, 200), 'original'),
                ]),
            ];
        }

        if ($objects) {
            $body = [
                'objects' => $objects
            ];
            $this->request($parent, '/v1/content', $body, 'post');
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
                $body = [
                    'objects' => $batch
                ];
                $this->request($website, '/v1/content/delete', $body, 'delete', $compressed);
                print "Deleted count " . count($batch) . " from website: {$website->name}\n";
            }
        });
    }

    public function deleteContentFromWebpage(Webpage $webpage): void
    {
        $website = $webpage->website;
        if ($webpage->model instanceof Product) {
            $objects = [
                [
                    "type" => "item",
                    "identity" => $this->getWebpageUrl($webpage),
                ],
            ];

            $body = [
                'objects' => $objects
            ];

            $this->request($website, '/v1/content/delete', $body, 'delete');

        } else {
            $body = [
                'objects' => [
                    [
                        "type" => 'category',
                        "identity" => $webpage->url,
                    ]
                ]
            ];
            $this->request($website, '/v1/content/delete', $body, 'delete');
        }
    }

    public function deleteContentManual(Webpage $webpage, array $object): void
    {
        $this->request(
            $webpage,
            '/v1/content/delete',
            [
                'objects' => [
                    $object
                ]
            ],
            'delete'
        );
    }

    public function getObjectFromWebpage(Webpage $webpage): array
    {
        $model = $webpage->model;

        if ($model instanceof Product) {
            $family = $model->family;
            $department = $family?->department;
            $subDepartment = $model->subDepartment;
            $identity = "$webpage->group_id:$webpage->organisation_id:$webpage->shop_id:{$webpage->website->id}:$webpage->id";
            $brand = $model->getBrand();
            $brandObject = null;
            if ($brand) {
                $url = '/search?lb.f[]=brand:' . $brand->name . '&q=' . $brand->name;
                $brandObject = [
                    "identity" => $url,
                    "type" => "brand",
                    "fields" => array_filter([
                        "title" => $brand->name,
                        "web_url" => $url,
                        "image_link" => Arr::get($brand->imageSources(200, 200), 'original'),
                    ]),
                ];
            }
            $object =  [
                "identity" => $identity,
                "type" => "item",
                "fields" => array_filter([
                    "title" => $webpage->title,
                    "web_url" => $this->getWebpageUrl($webpage),
                    "availability" => intval($model->state == ProductStateEnum::ACTIVE),
                    "stock_qty" => $model->available_quantity ?? 0,
                    "price" => $model->price ?? 0,
                    "formatted_price" => $model->currency->symbol . $model->price . '/' . $model->unit,
                    "image_link" => Arr::get($model->imageSources(200, 200), 'original'),
                    "product_code" => $model->code,
                    "introduced_at" => $model?->created_at ? $model->created_at->format('c') : null,
                    "description" => $model->description,
                ]),
                ...($family || $department || $subDepartment || $brandObject ? [
                    "nested" => array_values(array_filter([
                        ($brandObject ? $brandObject : []),
                        (
                            $family && $family?->webpage ?
                        [
                            "type" => "category",
                            "identity" => $this->getWebpageUrl($family?->webpage),
                            "fields" => array_filter([
                                "title" => $family?->webpage?->title,
                                "web_url" => $this->getWebpageUrl($family?->webpage),
                                "description" => $family?->webpage?->description,
                                "image_link" => Arr::get($family?->imageSources(200, 200), 'original'),
                            ])
                        ] : []
                        ),
                        ($subDepartment && $subDepartment?->webpage ?
                        [
                            "type" => "sub_department",
                            "identity" => $this->getWebpageUrl($subDepartment?->webpage),
                            "fields" => array_filter([
                                "title" => $subDepartment?->webpage?->title,
                                "web_url" => $this->getWebpageUrl($subDepartment?->webpage),
                                "description" => $subDepartment?->webpage?->description,
                                "image_link" => Arr::get($subDepartment?->imageSources(200, 200), 'original'),
                            ]),
                        ] : []),
                        ($department && $department?->webpage ?
                            [
                                "type" => "department",
                                "identity" => $this->getWebpageUrl($department?->webpage),
                                "fields" => array_filter([
                                    "title" => $department?->webpage?->title,
                                    "web_url" => $this->getWebpageUrl($department?->webpage),
                                    "description" => $department?->webpage?->description,
                                    "image_link" => Arr::get($department?->imageSources(200, 200), 'original'),
                                ]),
                            ]
                        : []),

                    ])),
                ] : []),

            ];

            return $object;
        } else {
            $modelWebpage = $model?->webpage;
            $type = null;
            if (!$modelWebpage) {
                if ($webpage->type == WebpageTypeEnum::BLOG) {
                    $type = 'news';
                } else {
                    return [];
                }
            }
            return [
                "identity" => $this->getWebpageUrl($modelWebpage),
                "type" => $type ?? $this->getType($model),
                "fields" => array_filter([
                    "title" => $modelWebpage->title,
                    "web_url" => $this->getWebpageUrl($modelWebpage),
                    "description" => $modelWebpage->description,
                    "image_link" => Arr::get($model->imageSources(200, 200), 'original'),
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

        switch ($model->type) {
            case ProductCategoryTypeEnum::DEPARTMENT:
                return 'department';
            case ProductCategoryTypeEnum::SUB_DEPARTMENT:
                return 'sub_department';
            default:
                return 'category';
        }
    }

}
