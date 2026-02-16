<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Exports\Portfolio\DropshippingClientTemplateExport;
use App\Exports\Portfolio\PortfolioTemplateExport;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTemplateRetinaPortfolios extends RetinaAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): BinaryFileResponse
    {
        $type = $modelData['type'];

        $fileName = 'dropshipping_portfolio_template.' . $type;

        return Excel::download(new DropshippingClientTemplateExport(), $fileName);    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): BinaryFileResponse
    {
        return $this->handle($request->all());
    }
}
