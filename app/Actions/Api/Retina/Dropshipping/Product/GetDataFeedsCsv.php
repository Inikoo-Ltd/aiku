<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfoliosCSV;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GetDataFeedsCsv extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): string
    {
        $action = DownloadPortfoliosCSV::make();
        $action->customer = $this->customer;
        $action->asAction = true;

        return $action->handle($customerSalesChannel, 'csv_content');
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisationFromDropshipping($request);

        $csvContent = $this->handle($this->customerSalesChannel);

        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'inline; filename="portfolio_data_feed.csv"');
    }
}

