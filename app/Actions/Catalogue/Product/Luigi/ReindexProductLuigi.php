<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Product\Luigi;

use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexProductLuigi
{
    use AsAction;

    public string $commandSignature = 'xxxxx';

    public function digest($key, $method, $endpoint, $date)
    {
        $content_type = 'application/json;charset=utf-8';

        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        $signature = trim(base64_encode(hash_hmac('sha256', $data, $key, true)));

        return $signature;
    }


    public function asCommand($command)
    {
        $body = [
            'objects' => [
                [
                    'identity' => '8a4d91b896fae60341ee51fb4c86facd',
                    'type' => 'item',
                    'fields' => [
                    'title' => '@@@###Blue Socks###@@@',
                    'web_url' => '/products/1',
                    'description' => 'Comfortable socks',
                    'price' => '2.9 EUR',
                    'color' => 'blue',
                    'material' => 'wool',
                    ],
                ],
            ]
        ];
        $offsetSeconds = 6;
        $date = gmdate('D, d M Y H:i:s', time() + $offsetSeconds).' GMT';




        $privateKey = ''; // 'privateKey' from the API key
        $publicKey = ''; // trackingid
        $signature  = $this->digest(
            $privateKey,
            'POST',
            '/v1/content',
            $date
        );

        $response = Http::withHeaders([
            'Accept-Encoding' => 'gzip',
            'Content-Type' => 'application/json;charset=utf-8',
            'Date' => $date,
            'Authorization' => "ApiAuth {$publicKey}:{$signature}",
        ])->post('https://live.luigisbox.com/v1/content', $body);

        dd($response->body(), $response->json(), $response->status());
    }
}
