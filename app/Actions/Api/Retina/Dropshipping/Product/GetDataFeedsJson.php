<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\Retina\Dropshipping\Portfolio\PortfoliosJsonExport;
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
        return response()->streamDownload(function () use ($customer, $customerSalesChannel) {
            $jsonData = PortfoliosJsonExport::make()->handle($customer, $customerSalesChannel);
            echo json_encode($jsonData, JSON_PRETTY_PRINT);
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
