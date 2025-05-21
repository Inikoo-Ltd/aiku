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

    public string $commandSignature = 'test_search';



    public function digest($key, $content_type, $method, $endpoint, $date): string
    {
        $data = "{$method}\n{$content_type}\n{$date}\n{$endpoint}";

        return trim(base64_encode(hash_hmac('sha256', $data, $key, true)));
    }

    /**
     * @throws \Laravel\Octane\Exceptions\DdException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand()
    {
        $content_type = 'application/json; charset=utf-8';

        $body          = [
            'objects' => [
                [
                    'identity' => '8a4d91b896fae60341ee51fb4c86facd',
                    'type'     => 'item',
                    'fields'   => [
                        'title'       => '@@@###Blue Socks###@@@',
                        'web_url'     => '/products/1',
                        'description' => 'Comfortable socks',
                        'price'       => '2.9 EUR',
                        'color'       => 'blue',
                        'material'    => 'wool',
                    ],
                ],
            ]
        ];
        $offsetSeconds = 0;
        $date          = gmdate('D, d M Y H:i:s', time() + $offsetSeconds).' GMT';

        $endPoint = '/v1/content';

        $publicKey  = env('LS_TRACKING_ID');
        $privateKey = env('LS_PRIVATE_KEY');
        $signature  = $this->digest(
            $privateKey,
            $content_type,
            'POST',
            $endPoint,
            $date
        );


        $response = Http::withHeaders([
            'Accept-Encoding' => 'gzip',
            'Content-Type'    => $content_type,
            'Date'            => $date,
            'Authorization'   => "Hello {$publicKey}:{$signature}",
        ])->post('https://live.luigisbox.com/'.$endPoint, $body);

        dd($response->body(), $response->json(), $response->status());
    }
}
