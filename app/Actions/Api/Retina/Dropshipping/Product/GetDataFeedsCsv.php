<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-07-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\Retina\Dropshipping\Portfolio\PortfoliosCsvOrExcelExport;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Maatwebsite\Excel\Facades\Excel;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GetDataFeedsCsv extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): BinaryFileResponse
    {
        $customer = $customerSalesChannel->customer;
        $fileName = 'data_feed_' . $customer->slug . '_' . now()->format('Ymd') . '.csv';

        return Excel::download(new PortfoliosCsvOrExcelExport($customer, $customerSalesChannel), $fileName, null, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function asController(ActionRequest $request)
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($this->customerSalesChannel);
    }
}
