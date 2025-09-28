<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 14:55:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Models\Accounting\Invoice;

class CalculateInvoiceTotals extends OrgAction
{
    public function handle(Invoice $invoice): Invoice
    {
        $transactions = $invoice->invoiceTransactions;

        $taxRate = $invoice->taxCategory->rate;


        $rentalNet     = $transactions->whereIn('model_type', ['Pallet', 'StoredItem', 'Space', 'Rental'])->sum('net_amount');
        $rentalGross   = $transactions->whereIn('model_type', ['Pallet', 'StoredItem', 'Space', 'Rental'])->sum('gross_amount');
        $goodsNet      = $transactions->where('model_type', 'Product')->sum('net_amount');
        $goodsGross    = $transactions->where('model_type', 'Product')->sum('gross_amount');
        $serviceNet    = $transactions->where('model_type', 'Service')->sum('net_amount');
        $serviceGross  = $transactions->where('model_type', 'Service')->sum('gross_amount');
        $shippingNet   = $transactions->where('model_type', 'ShippingZone')->sum('net_amount');
        $shippingGross = $transactions->where('model_type', 'ShippingZone')->sum('gross_amount');
        $chargeNet     = $transactions->where('model_type', 'Charge')->sum('net_amount');
        $chargeGross   = $transactions->where('model_type', 'Charge')->sum('gross_amount');


        $netAmount   = $rentalNet + $goodsNet + $serviceNet + $shippingNet + $chargeNet;
        $grossAmount = $rentalGross + $goodsGross + $serviceGross + $shippingGross + $chargeGross;
        $taxAmount   = $netAmount * $taxRate;
        $totalAmount = $netAmount + $taxAmount;

        data_set($modelData, 'rental_amount', $rentalNet);
        data_set($modelData, 'net_amount', $netAmount);

        data_set($modelData, 'grp_net_amount', $netAmount * $invoice->grp_exchange);
        data_set($modelData, 'org_net_amount', $netAmount * $invoice->grp_exchange);


        data_set($modelData, 'total_amount', $totalAmount);
        data_set($modelData, 'effective_total', $totalAmount);
        data_set($modelData, 'tax_amount', $taxAmount);


        data_set($modelData, 'services_amount', $serviceNet);
        data_set($modelData, 'goods_amount', $goodsNet);
        data_set($modelData, 'gross_amount', $grossAmount);

        $invoice->update($modelData);

        GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);
        OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        CustomerHydrateInvoices::dispatch($invoice->customer)->delay($this->hydratorsDelay);
        ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);

        return $invoice;
    }

    public function action(Invoice $invoice, int $hydratorsDelay = 0): Invoice
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoice->shop, []);

        return $this->handle($invoice);
    }
}
