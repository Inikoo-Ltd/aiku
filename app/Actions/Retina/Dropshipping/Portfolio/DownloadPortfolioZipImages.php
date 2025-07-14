<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 17:38:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPortfolioZipImages extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        PortfoliosZipExport::run($customerSalesChannel);

        $filename = 'images.zip';
        $response = response()->streamDownload(function () use ($customerSalesChannel) {
            PortfoliosZipExport::make()->handle($customerSalesChannel);
        }, $filename);

        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }


    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->customerSalesChannel;
        if ($customerSalesChannel->customer_id != $request->user()->customer->id) {
            return false;
        }

        return true;
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($request);
        return $this->handle($customerSalesChannel);
    }
}
