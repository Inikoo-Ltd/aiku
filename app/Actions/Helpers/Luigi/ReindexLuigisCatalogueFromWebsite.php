<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Language\Luigi;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexLuigisCatalogueFromWebsite
{
    use AsAction;

    public string $commandSignature = 'test_search';



    public function digest($key, $content_type, $method, $endpoint, $date): string
    {
        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        return trim(base64_encode(hash_hmac('sha256', $data, $key, true)));
    }

    public function getAccessToken(Website $website): array
    {
        if (app()->environment('production')) {
            return array_filter([Arr::get($website->settings, 'luigisbox.tracking_id'), Arr::get($website->settings, 'luigisbox.private_key')]);
        } else {
            return [config('app.sandbox.luigisbox.tracking_id'), config('app.sandbox.luigisbox.private_key')];
        }
    }



    private function request(Website $website, string $endPoint, string $method = 'post', array $body)
    {
        $content_type = 'application/json; charset=utf-8';

        $offsetSeconds = 0;
        $date          = gmdate('D, d M Y H:i:s', time() + $offsetSeconds).' GMT';

        [$publicKey, $privateKey] = $this->getAccessToken($website);

        $signature  = $this->digest(
            $privateKey,
            $content_type,
            strtoupper($method),
            $endPoint,
            $date
        );


        $response = Http::withHeaders([
            'Accept-Encoding' => 'gzip',
            'Content-Type'    => $content_type,
            'Date'            => $date,
            'Authorization'   => "Hello {$publicKey}:{$signature}",
        ])->{strtolower($method)}('https://live.luigisbox.com/'.$endPoint, $body);


        if ($response->failed()) {
            throw new \Exception('Failed to send request to Luigi\'s Box API: '.$response->body());
        }

        if ($response->successful()) {
            return $response->json();
        }

    }

    public function handle(Website $website)
    {
        $webpages = $website->webpages()
            ->with('model')
            ->where('state', 'live')
            ->where('type', WebpageTypeEnum::CATALOGUE)
            ->where('model_type', 'Product')
            ->get();

        $body = [
            'objects' => []
        ];
        foreach ($webpages as $webpage) {

            $product = $webpage->model;


            $body['objects'][] = [
                "identity" => "$website->group_id:$website->organisation_id:$website->shop_id:$website->id:$webpage->id",
                "type" => "item",
                "fields" => array_filter([
                    "title" => $webpage->title,
                    "web_url" => $webpage->getFullUrl(),
                    "availability" => intval($product->state == ProductStateEnum::ACTIVE),
                    "stock_qty" => $product->available_quantity ?? 0,
                    "price" => $product->price ?? 0,
                    "formatted_price" => $product->currency->symbol . $product->price . '/' . $product->unit,
                    "image_link" => Arr::get($product->imageSources(400, 400), 'original'),
                    "product_code" => $product->code,
                    "introduced_at" => $product?->created_at ? $product->created_at->format('c') : null,
                    "category" => $product?->family?->webpage?->title,
                    "description" => $product->description,
                ]),
            ];
        }


        $this->request($website, '/v1/content', 'post', $body);
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand()
    {
        $website = Website::find(11);

        $this->handle($website);
    }
}
