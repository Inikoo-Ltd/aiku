<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Apr 2025 12:42:40 Malaysia Time, Kuala Lumpur, Malaysia
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

class RedirectInvoicesInCustomerLink extends OrgAction
{
    public function handle(Invoice $invoice): ?RedirectResponse
    {
        $shop = $invoice->shop;
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getFulfilmentRedirects($invoice, $shop);
        } else {
            return $this->getShopsRedirects($invoice, $shop);
        }
    }

    private function getFulfilmentRedirects(Invoice $invoice, Shop $shop): RedirectResponse
    {
        $url = route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
            $shop->organisation->slug,
            $shop->slug,
            $invoice->customer->slug
        ]);
        if ($invoice->type == InvoiceTypeEnum::INVOICE) {
            return Redirect::to($url . '?tab=invoices');
        } else {
            return Redirect::to($url . '?tab=refunds');
        }
    }

    private function getShopsRedirects(Invoice $invoice, Shop $shop): RedirectResponse
    {
        $url = route('grp.org.shops.show.crm.customers.show.invoices.index', [
            $shop->organisation->slug,
            $shop->slug,
            $invoice->customer->slug
        ]);
        if ($invoice->type == InvoiceTypeEnum::INVOICE) {
            return Redirect::to($url . '?tab=invoices');
        } else {
            return Redirect::to($url . '?tab=refunds');
        }
    }

    public function asController(Invoice $invoice, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

}
