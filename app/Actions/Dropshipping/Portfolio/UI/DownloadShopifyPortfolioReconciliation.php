<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\UI;

use App\Actions\Dropshipping\Portfolio\WithPortfolioReconciliationCsv;
use App\Actions\Dropshipping\Shopify\Product\ReconcileShopifyPortfolioConnections;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadShopifyPortfolioReconciliation extends OrgAction
{
    use WithCRMAuthorisation;
    use WithPortfolioReconciliationCsv;

    /**
     * A report the reconciliation could not stand behind is worse than no report, so a catalogue
     * that could not be read in full comes back as the plain reason rather than a CSV of rows
     * that would read as findings.
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): StreamedResponse|Response
    {
        $report = ReconcileShopifyPortfolioConnections::run($customerSalesChannel);

        if (!$report['complete']) {
            return response($report['reason'], 422)->header('Content-Type', 'text/plain');
        }

        $rows = $report['rows'];

        return response()->streamDownload(
            function () use ($rows) {
                $handle = fopen('php://output', 'w');

                $this->writeReconciliationCsv($handle, $rows);

                fclose($handle);
            },
            $this->reconciliationFilename($customerSalesChannel->slug),
            ['Content-Type' => 'text/csv']
        );
    }

    public function asController(
        Organisation $organisation,
        Shop $shop,
        Customer $customer,
        CustomerSalesChannel $customerSalesChannel,
        ActionRequest $request
    ): StreamedResponse|Response {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerSalesChannel);
    }
}
