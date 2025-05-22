<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Luigi\Trait;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
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
        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        return trim(base64_encode(hash_hmac('sha256', $data, $key, true)));
    }

    public function getAccessToken(Website|Webpage $parent): array
    {
        $website = $parent instanceof Website ? $parent : $parent->website;
        if (app()->environment('production')) {
            return array_filter([Arr::get($website->settings, 'luigisbox.tracking_id'), Arr::get($website->settings, 'luigisbox.private_key')]);
        } else {
            return [config('app.sandbox.luigisbox.tracking_id'), config('app.sandbox.luigisbox.private_key')];
        }
    }

    private function request(Website|Webpage $parent, string $endPoint, string $method = 'post', array $body, $compressed = false)
    {
        $content_type = 'application/json; charset=utf-8';

        $offsetSeconds = 6;
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
            throw new \Exception('Failed to send request to Luigi\'s Box API: '.$response->body());
        }

        if ($response->successful()) {
            return $response->json();
        }

    }

    public function reindex(Website|Webpage $parent)
    {
        if ($parent instanceof Website) {
            $website = $parent;
            $website->webpages()
            ->with('model')
            ->with('model.family.webpage')
            ->where('state', 'live')
            ->where('type', WebpageTypeEnum::CATALOGUE)
            ->where('model_type', 'Product')
            ->chunk(1000, function ($webpages) use ($website) {
                $objects = [];
                foreach ($webpages as $webpage) {
                    $product = $webpage->model;
                    $family = $product?->family;

                    $objects[] = [
                        "identity" => "$website->group_id:$website->organisation_id:$website->shop_id:$website->id:$webpage->id",
                        "type" => "item",
                        "fields" => array_filter([
                        "title" => $webpage->title,
                        "web_url" => $webpage->getFullUrl(),
                        "availability" => intval($product->state == ProductStateEnum::ACTIVE),
                        "stock_qty" => $product->available_quantity ?? 0,
                        "price" => $product->price ?? 0,
                        "formatted_price" => $product->currency->symbol . $product->price . '/' . $product->unit,
                        "image_link" => Arr::get($product->imageSources(200, 200), 'original'),
                        "product_code" => $product->code,
                        "introduced_at" => $product?->created_at ? $product->created_at->format('c') : null,
                        "description" => $product->description,
                        ]),
                        ...($family && $family?->webpage ? [
                        "nested" => [
                            [
                                "type" => "category",
                                "identity" => $family?->webpage?->url,
                                "fields" => array_filter([
                                    "title" => $family?->webpage?->title,
                                    "web_url" => $family?->webpage?->getFullUrl(),
                                    "description" => $family?->webpage?->description,
                                    "image_link" => Arr::get($family?->imageSources(200, 200), 'original'),
                                ]),
                            ],
                        ],
                        ] : []),
                    ];
                }

                $body = [
                    'objects' => $objects
                ];
                $compressed = count($objects) >= 1000;
                $this->request($website, '/v1/content', 'post', $body, $compressed);
            });

            return;
        } else {
            $webpage = $parent;
            $product = $webpage->model;
            $family = $product?->family;

            $objects = [
                [
                    "identity" => "$webpage->group_id:$webpage->organisation_id:$webpage->shop_id:$webpage->id:$webpage->id",
                    "type" => "item",
                    "fields" => array_filter([
                        "title" => $webpage->title,
                        "web_url" => $webpage->getFullUrl(),
                        "availability" => intval($product->state == ProductStateEnum::ACTIVE),
                        "stock_qty" => $product->available_quantity ?? 0,
                        "price" => $product->price ?? 0,
                        "formatted_price" => $product->currency->symbol . $product->price . '/' . $product->unit,
                        "image_link" => Arr::get($product->imageSources(200, 200), 'original'),
                        "product_code" => $product->code,
                        "introduced_at" => $product?->created_at ? $product->created_at->format('c') : null,
                        "description" => $product->description,
                    ]),
                    ...($family && $family?->webpage ? [
                        "nested" => [
                            [
                                "type" => "category",
                                "identity" => $family?->webpage?->url,
                                "fields" => array_filter([
                                    "title" => $family?->webpage?->title,
                                    "web_url" => $family?->webpage?->getFullUrl(),
                                    "description" => $family?->webpage?->description,
                                    "image_link" => Arr::get($family?->imageSources(200, 200), 'original'),
                                ]),
                            ],
                        ],
                    ] : []),
                ]
            ];

            if (count($objects) > 1000) {
                throw new \Exception('Too many objects to reindex');
            }

            $body = [
                'objects' => $objects
            ];
            return $this->request($parent, '/v1/content', 'post', $body, true);
        }
    }

}
