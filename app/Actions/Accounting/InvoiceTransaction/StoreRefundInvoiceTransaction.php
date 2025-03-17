<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Jan 2025 16:38:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Accounting\Invoice\CalculateInvoiceTotals;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreRefundInvoiceTransaction extends OrgAction
{
    use WithFixedAddressActions;
    use WithOrderExchanges;
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund, InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $taxCategory = $invoiceTransaction->taxCategory;
        if ($taxCategory) {
            $taxRate = $taxCategory->rate;
        } else {
            $taxRate = 0;
        }

        $grossAmount = - Arr::get($modelData, 'gross_amount', 0);
        $netAmount = $grossAmount / (1 + $taxRate);
        data_set($modelData, 'net_amount', $netAmount);


        $orgExchange = GetCurrencyExchange::run($refund->currency, $refund->organisation->currency);
        $grpExchange = GetCurrencyExchange::run($refund->currency, $refund->group->currency);

        data_set($modelData, 'grp_net_amount', $netAmount * $grpExchange);
        data_set($modelData, 'org_net_amount', $netAmount * $orgExchange);


        if ($invoiceTransaction->quantity == 0) {
            $quantity = 0;
        } else {
            $unitPrice = $invoiceTransaction->net_amount / $invoiceTransaction->quantity;
            $quantity  = $netAmount / $unitPrice;
        }
        data_set($modelData, 'quantity', $quantity);



        data_set($modelData, 'invoice_id', $refund->id);
        data_set($modelData, 'group_id', $invoiceTransaction->group_id);
        data_set($modelData, 'organisation_id', $invoiceTransaction->organisation_id);
        data_set($modelData, 'shop_id', $invoiceTransaction->shop_id);
        data_set($modelData, 'customer_id', $invoiceTransaction->customer_id);
        data_set($modelData, 'date', now());



        data_set($modelData, 'model_type', $invoiceTransaction->model_type);
        data_set($modelData, 'tax_category_id', $invoiceTransaction->tax_category_id);
        data_set($modelData, 'model_id', $invoiceTransaction->model_id);
        data_set($modelData, 'asset_id', $invoiceTransaction->asset_id);
        data_set($modelData, 'department_id', $invoiceTransaction->department_id);
        data_set($modelData, 'order_id', $invoiceTransaction->order_id);
        data_set($modelData, 'transaction_id', $invoiceTransaction->transaction_id);
        data_set($modelData, 'family_id', $invoiceTransaction->family_id);
        data_set($modelData, 'historic_asset_id', $invoiceTransaction->historic_asset_id);

        data_set($modelData, 'in_process', true);



        $invoiceTransaction = $invoiceTransaction->transactionRefunds()->create($modelData);

        CalculateInvoiceTotals::run($refund);

        return $invoiceTransaction;
    }

    public function rules(): array
    {
        return [
            'gross_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, InvoiceTransaction $invoiceTransaction, ActionRequest $request): void
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $request);
        $this->handle($refund, $invoiceTransaction, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund, InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $modelData);

        return $this->handle($refund, $invoiceTransaction, $this->validatedData);
    }

}
