<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\UI;

use App\Actions\Dropshipping\Portfolio\WithDownloadPortfolios;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DownloadPortfoliosInCustomerSalesChannel extends OrgAction
{
    use WithCRMAuthorisation;
    use WithDownloadPortfolios;

    public function asController(
        Organisation $organisation,
        Shop $shop,
        Customer $customer,
        CustomerSalesChannel $customerSalesChannel,
        ActionRequest $request
    ): BinaryFileResponse|Response {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerSalesChannel, ...$this->getDownloadPortfoliosInput($request));
    }
}
