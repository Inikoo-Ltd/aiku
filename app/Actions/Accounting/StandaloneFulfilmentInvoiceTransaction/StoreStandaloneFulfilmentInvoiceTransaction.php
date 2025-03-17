<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-08h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoiceTransaction;

use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\CalculateStandaloneFulfilmentInvoiceTotals;
use App\Actions\OrgAction;
use App\Enums\Fulfilment\RentalAgreementClause\RentalAgreementCauseStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\HistoricAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreStandaloneFulfilmentInvoiceTransaction extends OrgAction
{
    public function handle(Invoice $invoice, HistoricAsset $historicAsset, array $modelData): InvoiceTransaction
    {
        $grossAmount = $historicAsset->price * Arr::get($modelData, 'quantity');
        data_set($modelData, 'tax_category_id', $invoice->tax_category_id);
        data_set($modelData, 'gross_amount', $grossAmount);

        // here we calculate the discounts from clauses
        $netAmount = $this->getDiscounts($invoice, $grossAmount, $historicAsset->asset_id);

        data_set($modelData, 'net_amount', $netAmount);
        data_set($modelData, 'in_process', true);

        $invoiceTransaction = StoreInvoiceTransaction::make()->action($invoice, $historicAsset, $modelData);

        CalculateStandaloneFulfilmentInvoiceTotals::run($invoice);
        return $invoiceTransaction;
    }


    public function getDiscounts(Invoice $invoice, $grossAmount, int $assetID)
    {

        $rentalAgreementClause = $invoice->customer->fulfilmentCustomer->rentalAgreementClauses()
            ->where('state', RentalAgreementCauseStateEnum::ACTIVE)
            ->where('asset_id', $assetID)
            ->first();

        $percentageOff = 0;
        if ($rentalAgreementClause) {

            $percentageOff = $rentalAgreementClause->percentage_off / 100;

        }

        return $grossAmount - ($grossAmount * $percentageOff);
    }

    public function rules(): array
    {
        return [
            'pallet_id'       => ['sometimes'],
            'handle_date'     => ['sometimes'],
            'quantity' => ['required', 'numeric'],
        ];
    }

    public function asController(Invoice $invoice, HistoricAsset $historicAsset, ActionRequest $request): InvoiceTransaction
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $historicAsset, $this->validatedData);
    }

    public function action(Invoice $invoice, HistoricAsset $historicAsset, array $modelData): InvoiceTransaction
    {
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $historicAsset, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }


}
