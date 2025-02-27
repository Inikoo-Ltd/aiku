<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-08h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction;

use App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\CalculateStandaloneFulfilmentInvoiceTotals;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithOrderExchanges;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateStandaloneFulfilmentInvoiceTransaction extends OrgAction
{
    use WithOrderExchanges;
    use WithNoStrictRules;

    public function handle(InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        if (Arr::exists($modelData, 'net_amount')) {
            $netAmount = Arr::get($modelData, 'net_amount');
            $quantity = $netAmount / $invoiceTransaction->historicAsset->price;
            data_set($modelData, 'quantity', $quantity);
        }

        $invoiceTransaction = UpdateInvoiceTransaction::make()->action($invoiceTransaction, $modelData);

        $invoiceTransaction = CalculateStandaloneFulfilmentInvoiceTransactionAmounts::make()->action($invoiceTransaction);
        
        CalculateStandaloneFulfilmentInvoiceTotals::run($invoiceTransaction->invoice);

        return $invoiceTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity'            => ['sometimes', 'numeric', 'min:0'],
            'net_amount'          => ['sometimes', 'numeric'],
        ];
        return $rules;
    }


    public function asController(InvoiceTransaction $invoiceTransaction, ActionRequest $request)
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $request);

        $this->handle($invoiceTransaction, $this->validatedData);
    }


}
