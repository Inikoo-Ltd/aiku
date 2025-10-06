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
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GetDataFeedsCsv extends RetinaApiAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): BinaryFileResponse
    {
        return DownloadPortfoliosCSV::make()->handle($customerSalesChannel);
    }

    public function asController(ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($this->customerSalesChannel);
    }
}
