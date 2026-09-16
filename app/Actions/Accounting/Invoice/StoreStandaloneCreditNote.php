<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 16:10:00 Central European Summer Time, Mexico City, Mexico
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromAdjustment;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Ordering\Adjustment\StoreAdjustment;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * A credit note with no original invoice: a goodwill or compensation credit that accounts
 * need documented with a number in the refund sequence and a net/VAT/gross split (HELP-3025).
 */
class StoreStandaloneCreditNote extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, array $modelData): Invoice
    {
        $grossAmount = round(abs(Arr::get($modelData, 'amount')), 2);

        return DB::transaction(function () use ($customer, $grossAmount, $modelData) {
            $creditNote = StoreInvoice::make()->action($customer, [
                'reference'    => GetSerialReference::run(
                    container: $customer->shop,
                    modelType: SerialReferenceModelEnum::REFUND
                ),
                'type'         => InvoiceTypeEnum::REFUND,
                'currency_id'  => $customer->shop->currency_id,
                'net_amount'   => 0,
                'total_amount' => 0,
                'gross_amount' => 0,
                'tax_amount'   => 0,
                'footer'       => Arr::get($modelData, 'reason', ''),
            ], hydratorsDelay: $this->hydratorsDelay);

            $netAmount = $this->netForGross($grossAmount, (float)$creditNote->taxCategory->rate);

            $adjustment = StoreAdjustment::make()->action($customer->shop, [
                'type'       => AdjustmentTypeEnum::CREDIT,
                'net_amount' => -$netAmount,
            ]);

            StoreInvoiceTransactionFromAdjustment::make()->action($creditNote, $adjustment, [
                'tax_category_id' => $creditNote->tax_category_id,
                'quantity'        => 1,
                'net_amount'      => -$netAmount,
                'gross_amount'    => -$netAmount,
            ]);

            return CalculateInvoiceTotals::make()->action($creditNote, $this->hydratorsDelay);
        });
    }

    /**
     * The staff member types what the customer gets back, tax included. The net is found so that
     * net + rounded tax lands exactly on that figure, nudging by a penny when plain division does not.
     */
    private function netForGross(float $grossAmount, float $rate): float
    {
        $netAmount = round($grossAmount / (1 + $rate), 2);

        foreach ([0, 0.01, -0.01] as $delta) {
            $candidate = round($netAmount + $delta, 2);
            if (round($candidate + round($candidate * $rate, 2), 2) == $grossAmount) {
                return $candidate;
            }
        }

        return $netAmount;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, array $modelData, int $hydratorsDelay = 0): Invoice
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $this->validatedData);
    }
}
