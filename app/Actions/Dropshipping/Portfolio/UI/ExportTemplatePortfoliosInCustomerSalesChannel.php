<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Exports\Portfolio\PortfolioTemplateExport;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTemplatePortfoliosInCustomerSalesChannel extends OrgAction
{
    use WithCRMAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(string $type): BinaryFileResponse
    {
        return Excel::download(new PortfolioTemplateExport(), 'dropshipping_portfolio_template.'.$type);
    }

    /**
     * @throws \Throwable
     */
    public function asController(
        Organisation $organisation,
        Shop $shop,
        Customer $customer,
        CustomerSalesChannel $customerSalesChannel,
        ActionRequest $request
    ): BinaryFileResponse {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($request->get('type', 'xlsx'));
    }
}
