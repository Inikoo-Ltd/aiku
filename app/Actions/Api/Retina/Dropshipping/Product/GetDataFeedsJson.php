<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Symfony\Component\HttpFoundation\Response;
use Lorisleiva\Actions\ActionRequest;

class GetDataFeedsJson extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): Response
    {
        $customer = $customerSalesChannel->customer;
        $fileName = 'data_feed_' . $customer->slug . '_' . now()->format('Ymd') . '_json.txt';
        $file     = fopen(StoreDataFeed::make()->storedPath($customerSalesChannel, 'json'), 'r');

        return response()->streamDownload(function () use ($file) {
            fpassthru($file);
            fclose($file);
        }, $fileName, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($this->customerSalesChannel);
    }
}
