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

    // public function digest($key, $content_type, $method, $endpoint, $date): string
    // {
    //     $data = "$method\n$content_type\n$date\n$endpoint";

    //     return trim(base64_encode(hash_hmac('sha256', $data, $key, true)));
    // }

    public function digest($key, $content_type, $method, $endpoint, $date): string
    {
        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        $signature = trim(base64_encode(hash_hmac('sha256', $data, $key, true)));

        return $signature;
    }

    public function getAccessToken(Website|Webpage $parent): array
    {
        $website = $parent instanceof Website ? $parent : $parent->website;

        if (app()->environment('production')) {
            $trackerId = Arr::get($website->settings, 'luigisbox.tracker_id');
            $privateKey = Arr::get($website->settings, 'luigisbox.private_key');

            Log::info('Tracker ID - PROD: ' . $trackerId);
            Log::info('Private Key: ' . $privateKey);

            return array_filter([$trackerId, $privateKey]);
        } else {
            // $trackerId = config('app.sandbox.luigisbox.tracker_id');
            // $privateKey = config('app.sandbox.luigisbox.private_key');

            $trackerId = "483878-777949";
            $privateKey = "b3d22400022aca2e0a656163aa3790e6516fcdc7897086688815131456df9c59";

            Log::info('Sandbox Tracker ID - NOT-PROD: ' . $trackerId);
            Log::info('Sandbox Private Key: ' . $privateKey);

            return array_filter([$trackerId, $privateKey]);
        }
    }

    /**
     * @throws \Exception
     */
    private function request(Website|Webpage $parent, string $endPoint, array $body, string $method = 'post', $compressed = false): void
    {
        $content_type = 'application/json; charset=utf-8';

        $offsetSeconds = 0;
        $date          = gmdate('D, d M Y H:i:s', time() + $offsetSeconds) . ' GMT';

        $accessToken = $this->getAccessToken($parent);

        Log::info('AccessToken: ' . json_encode($accessToken));

        [$publicKey, $privateKey] = $accessToken;

        $signature = $this->digest(
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
            'Authorization'   => "Hello $publicKey:$signature",
        ];

        Log::info('Header: ' . json_encode($header));

        Log::info('Header Authorization:' . $header['Authorization']);


        if ($compressed) {
            $header['Content-Encoding'] = 'gzip';
            $body                       = gzencode(json_encode($body), 9);
        } else {
            $body = json_encode($body);
        }

        Log::info('API Before Contacted');

        $response = Http::withHeaders($header)
            ->retry(3, 100)
            ->withBody($body, $content_type)
            ->{strtolower($method)}(
                'https://live.luigisbox.com/' . $endPoint
            );

        Log::info('API Contacted');

        if ($response->failed()) {
            throw new Exception('Failed to send request to Luigi\'s Box API: ' . $response->body());
        }
    }

    /**
     * @throws \Exception
     */
    public function reindex(Website|Webpage $parent): void
    {
        $accessToken = $this->getAccessToken($parent);
        if (count($accessToken) < 2) {
            Log::error('Luigi\'s Box access token is not configured properly');

            return;
        }
        if ($parent instanceof Website) {
            $website = $parent;
            $website->webpages()
                ->with('model')
                ->where('state', 'live')
                ->whereIn('type', [WebpageTypeEnum::CATALOGUE, WebpageTypeEnum::BLOG])
                ->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])
                ->chunk(1000, function ($webpages) use ($website) {
                    $objects = [];
                    foreach ($webpages as $webpage) {
                        $object = $this->getObjectFromWebpage($webpage);
                        if ($object) {
                            $objects[] = $object;
                        }
                    }

                    $body       = [
                        'objects' => $objects
                    ];
                    $compressed = count($objects) >= 1000;
                    try {
                        $this->request($website, '/v1/content', $body, 'post', $compressed);
                    } catch (Exception $e) {
                        print "Failed to reindex website $website->domain: " . $e->getMessage() . "\n";

                        return;
                    }
                });
        } else {
            $webpage = $parent;
            if ($webpage->type != WebpageTypeEnum::CATALOGUE && $webpage->type != WebpageTypeEnum::BLOG) {
                return;
            }

            $objects[] = $this->getObjectFromWebpage($webpage);

            $body = [
                'objects' => $objects
            ];
            try {
                $this->request($parent, '/v1/content', $body);
            } catch (Exception $e) {
                Log::error("Failed to reindex webpage $webpage->title: " . $e->getMessage());
            }
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

        return '/' . collect($segments)->implode('/');
    }


    public function reindexTags(Webpage|Website $parent, LaravelCollection $tags): void
    {

        $objects = [];
        foreach ($tags as $tag) {
            $url       = '/search?lb.t[]=tag:' . $tag->name . '&q=' . $tag->name;
            $objects[] = [
                "identity" => $url,
                "type"     => "tag",
                "fields"   => array_filter([
                    "slug"       => $this->getIdentityTag($tag),
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
            $this->request($parent, '/v1/content', $body);
        }
    }

    /**
     * @throws \Exception
     */
    public function reindexBrands(Webpage|Website $parent, LaravelCollection $brands): void
    {

        $objects = [];
        foreach ($brands as $brand) {
            $url       = '/search?lb.f[]=brand:' . $brand->name . '&q=' . $brand->name;
            $objects[] = [
                "identity" => $url,
                "type"     => "brand",
                "fields"   => array_filter([
                    "slug"       => $this->getIdentityBrand($brand),
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
            $this->request($parent, '/v1/content', $body);
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
                    $this->request($website, '/v1/content/delete', $body, 'delete', $compressed);
                    print "Deleted count " . count($batch) . " from website: $website->name\n";
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
                    "identity" => $this->getWebpageUrl($webpage),
                    //todo: "identity" => $this->getIdentity($webpage)
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
        $this->request($website, '/v1/content/delete', $body, 'delete');
    }

    /**
     * @throws \Exception
     */
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
        if ($product->family && $product->family->webpage && $product->family->webpage->state != WebpageStateEnum::LIVE) {
            $family     = $product->family;
            $familyData = [
                "type"     => "category",
                "identity" => $this->getWebpageUrl($family->webpage),
                "fields"   => array_filter([
                    "slug"        => $this->getIdentity($family->webpage),
                    "title"       => $family->webpage->title,
                    "web_url"     => $family->webpage->getCanonicalUrl(),
                    "description" => $family->webpage->description,
                    "image_link"  => Arr::get($family->imageSources(200, 200), 'original'),
                ])
            ];
        }


        $departmentData = [];
        if ($product->department && $product->department->webpage && $product->department->webpage->state != WebpageStateEnum::LIVE) {
            $department     = $product->department;
            $departmentData = [
                "type"     => "department",
                "identity" => $this->getWebpageUrl($department->webpage),
                "fields"   => array_filter([
                    "slug"        => $this->getIdentity($department->webpage),
                    "title"       => $department->webpage->title,
                    "web_url"     => $department->webpage->getCanonicalUrl(),
                    "description" => $department->webpage->description,
                    "image_link"  => Arr::get($department->imageSources(200, 200), 'original'),
                ]),
            ];
        }


        $subDepartmentData = [];
        if ($product->subDepartment && $product->subDepartment->webpage && $product->subDepartment->webpage->state != WebpageStateEnum::LIVE) {
            $subDepartment     = $product->subDepartment;
            $subDepartmentData = [
                "type"     => "sub_department",
                "identity" => $this->getWebpageUrl($subDepartment->webpage),
                "fields"   => array_filter([
                    "slug"        => $this->getIdentity($subDepartment->webpage),
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
            $url         = '/search?lb.f[]=brand:' . $brand->name . '&q=' . $brand->name;
            $brandObject = [
                "identity" => $url,
                "type"     => "brand",
                "fields"   => array_filter([
                    "slug"       => $this->getIdentityBrand($brand),
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
                $url          = '/search?lb.t[]=tag:' . $tag->name . '&q=' . $tag->name;
                $tagsObject[] = [
                    "identity" => $url,
                    "type"     => "tag",
                    "fields"   => array_filter([
                        "slug"       => $this->getIdentityTag($tag),
                        "title"      => $tag->name,
                        "web_url"    => $url,
                        "image_link" => Arr::get($tag->imageSources(200, 200), 'original'),
                    ]),
                ];
            }
        }
        $identity = "$webpage->group_id:$webpage->organisation_id:$webpage->shop_id:{$webpage->website->id}:$webpage->id";

        return [
            "identity" => $identity,
            "type"     => "item",
            "fields"   => array_filter([
                "slug"            => $this->getIdentity($webpage),
                "title"           => $webpage->title,
                "web_url"         => $webpage->getCanonicalUrl(),
                "availability"    => intval($product->state == ProductStateEnum::ACTIVE),
                "stock_qty"       => $product->available_quantity ?? 0,
                "price"           => (float)$product->price ?? 0,
                "formatted_price" => $product->currency->symbol . $product->price . '/' . $product->unit,
                "image_link"      => Arr::get($product->imageSources(200, 200), 'original'),
                "product_code"    => $product->code,
                "product_id"      => $product->id,
                "introduced_at"   => $product->created_at ? $product->created_at->format('c') : null,
                "description"     => $product->description,
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
                "identity" => $this->getWebpageUrl($modelWebpage),
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


    public function getAllObjects_backup(Website $parent): array
    {
        $accessToken = $this->getAccessToken($parent);

        if (count($accessToken) < 2) {
            Log::error('Luigi\'s Box access token is not configured properly');
            return [];
        }

        try {
            // Mengirim permintaan ke endpoint API
            $response = $this->request($parent, 'v1/content_export', [], 'get');

            // Log respons untuk debugging
            Log::info('Luigi\'s Box API Response: ', ['response' => $response]);

            // Validasi respons
            if (is_array($response) && !empty($response)) {
                return $response;
            }

            Log::warning('Luigi\'s Box API returned an empty or invalid response.');
            return [];
        } catch (Exception $e) {
            // Log kesalahan jika terjadi exception
            Log::error('Failed to fetch objects from Luigi\'s Box API: ' . $e->getMessage());
            return [];
        }
    }


    public function getAllObjects(Website $parent): array
    {

        $trackerId = "";
        $privateKey = "";

        // Log untuk memastikan Tracker ID dan Private Key digunakan
        Log::info('Hardcoded Tracker ID: ' . $trackerId);
        Log::info('Hardcoded Private Key: ' . $privateKey);

        try {
            // Header dan konfigurasi untuk permintaan
            $content_type = 'application/json; charset=utf-8';
            $date = gmdate('D, d M Y H:i:s T');

            // Membuat tanda tangan HMAC
            $endpoint = 'v1/search?tracker_id=' . $trackerId;
            $signature = $this->digest($privateKey, $content_type, 'GET', $endpoint, $date);

            // Header untuk permintaan
            $header = [
                'Accept-Encoding' => 'gzip',
                'Content-Type'    => $content_type,
                'Date'            => $date,
                'Authorization'   => "Hello $trackerId:$signature",
            ];

            Log::info('Endpoint: ' . json_encode($endpoint));
            Log::info('Request Headers: ' . json_encode($header));

            // Mengirim permintaan ke API Luigi's Box
            $response = Http::withHeaders($header)
                ->retry(3, 100)
                ->get('https://live.luigisbox.com/' . $endpoint);

            // Log respons untuk debugging
            Log::info('Luigi\'s Box API Response: ', ['status' => $response->status(), 'body' => $response->body()]);

            // Validasi respons
            if ($response->successful()) {
                $responseData = $response->json();
                if (is_array($responseData) && !empty($responseData)) {
                    return $responseData;
                }

                Log::warning('Luigi\'s Box API returned an empty or invalid response.');
                return [];
            }

            Log::error('Luigi\'s Box API request failed with status: ' . $response->status());
            return [];
        } catch (Exception $e) {
            // Log kesalahan jika terjadi exception
            Log::error('Failed to fetch objects from Luigi\'s Box API: ' . $e->getMessage());
            return [];
        }
    }
}
