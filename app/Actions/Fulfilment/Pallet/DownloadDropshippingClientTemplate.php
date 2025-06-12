<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\RetinaAction;
use App\Exports\Portfolio\DropshippingClientTemplateExport;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadDropshippingClientTemplate extends RetinaAction
{
    use AsAction;
    use WithAttributes;


    public function handle(): BinaryFileResponse
    {
        $fileName = 'dropshipping_client_template.xlsx';

        return Excel::download(new DropshippingClientTemplateExport(), $fileName);
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);
        return $this->handle();
    }
}
