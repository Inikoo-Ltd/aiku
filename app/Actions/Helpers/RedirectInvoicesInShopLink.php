<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Apr 2025 00:10:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectInvoicesInShopLink extends OrgAction
{
    public function handle(Invoice $invoice): ?RedirectResponse
    {
        $shop = $invoice->shop;
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getInvoiceRedirects($invoice, $shop);
        } else {
            return $this->getRefundRedirects($invoice, $shop);
        }
    }

    private function getInvoiceRedirects(Invoice $invoice, Shop $shop): RedirectResponse
    {
        if ($invoice->type == InvoiceTypeEnum::INVOICE) {
            return Redirect::route(
                'grp.org.fulfilments.show.operations.invoices.invoices.index',
                [
                    $shop->organisation->slug,
                    $shop->fulfilment->slug,
                ]
            );
        } else {
            return Redirect::route(
                'grp.org.fulfilments.show.operations.invoices.refunds.index',
                [
                    $shop->organisation->slug,
                    $shop->fulfilment->slug,
                ]
            );
        }
    }

    private function getRefundRedirects(Invoice $invoice, Shop $shop): RedirectResponse
    {
        if ($invoice->type == InvoiceTypeEnum::INVOICE) {
            return Redirect::route(
                'grp.org.shops.show.dashboard.invoices.index',
                [
                    $shop->organisation->slug,
                    $shop->slug
                ]
            );
        } else {
            return Redirect::route(
                'grp.org.shops.show.dashboard.invoices.refunds.index',
                [
                    $shop->organisation->slug,
                    $shop->slug
                ]
            );
        }
    }

    public function asController(Invoice $invoice, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

}
