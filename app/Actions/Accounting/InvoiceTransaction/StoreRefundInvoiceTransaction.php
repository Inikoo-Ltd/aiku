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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Validator;

class StoreRefundInvoiceTransaction extends OrgAction
{
    use WithFixedAddressActions;
    use WithOrderExchanges;
    use WithNoStrictRules;

    private InvoiceTransaction $invoiceTransaction;
    private Invoice $refund;

    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund, InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $isRefundAll = Arr::pull($modelData, 'refund_all', false);

        $netAmount = Arr::get($modelData, 'net_amount', 0) * -1;
        if ($netAmount === 0) {
            $invoiceTransaction->transactionRefunds()->where('invoice_id', $refund->id)->forceDelete();
            if (!$isRefundAll) {
                CalculateInvoiceTotals::run($refund);
            }

            return $invoiceTransaction;
        }

        $totalTrRefunded = $invoiceTransaction->transactionRefunds->where('in_process', false)->sum('net_amount');

        if (abs($netAmount) == $invoiceTransaction->net_amount) {
            $netAmount = (abs($netAmount) - abs($totalTrRefunded)) * -1;

            if ($netAmount == 0) {
                if (!$isRefundAll) {
                    CalculateInvoiceTotals::run($refund);
                }

                return $invoiceTransaction;
            }
        }

        $invoiceTransaction->transactionRefunds()->where('invoice_id', $refund->id)->forceDelete();

        data_set($modelData, 'net_amount', $netAmount);

        $orgExchange = GetCurrencyExchange::run($refund->currency, $refund->organisation->currency);
        $grpExchange = GetCurrencyExchange::run($refund->currency, $refund->group->currency);

        data_set($modelData, 'grp_net_amount', $netAmount * $grpExchange);
        data_set($modelData, 'org_net_amount', $netAmount * $orgExchange);


        if ($invoiceTransaction->quantity == 0) {
            $quantity = 0;
        } else {
            $unitNetPrice = $invoiceTransaction->net_amount / $invoiceTransaction->quantity;

            $quantity = $netAmount / $unitNetPrice;
        }


        data_set($modelData, 'quantity', $quantity);


        data_set($modelData, 'original_invoice_transaction_id', $refund->id);
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

        $refund->refresh();

        if (!$isRefundAll) {
            CalculateInvoiceTotals::run($refund);
        }

        return $invoiceTransaction;
    }

    public function rules(): array
    {
        return [
            'net_amount' => ['required', 'numeric', 'gte:0'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function afterValidator(Validator $validator): void
    {
        $totalTrRefunded = $this->invoiceTransaction->transactionRefunds->where('in_process', false)->sum('net_amount');

        $netAmount = $this->get('net_amount', 0) * -1;
        $totalTr   = $this->invoiceTransaction->net_amount - (abs($totalTrRefunded) + abs($netAmount));

        if (abs($netAmount) == $this->invoiceTransaction->net_amount) {
            $totalTr = 0;
        }

        if ($totalTr < 0) {
            throw ValidationException::withMessages(
                [
                    'message' => [
                        'net_amount' => 'Refund amount exceeds or already refunded in other refund',
                    ]
                ]
            );
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, InvoiceTransaction $invoiceTransaction, ActionRequest $request): void
    {
        $this->invoiceTransaction = $invoiceTransaction;
        $this->refund             = $refund;
        $this->initialisationFromShop($invoiceTransaction->shop, $request);
        $this->handle($refund, $invoiceTransaction, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(Invoice $refund, InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $this->invoiceTransaction = $invoiceTransaction;
        $this->refund             = $refund;
        $this->initialisationFromShop($invoiceTransaction->shop, $modelData);

        return $this->handle($refund, $invoiceTransaction, $this->validatedData);
    }

}
