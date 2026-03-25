<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 15:22:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\InvoiceTransaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class UpdateInvoiceTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $oldDate = $invoiceTransaction->date;

        $invoiceTransaction = $this->update($invoiceTransaction, $modelData, ['data']);

        $changes = $invoiceTransaction->getChanges();

        SyncInvoiceTransactionTradeUnitBridges::dispatch($invoiceTransaction->id);
        SyncInvoiceTransactionOrgStockBridges::dispatch($invoiceTransaction->id);
        SyncInvoiceTransactionStockBridges::dispatch($invoiceTransaction->id);

        if (Arr::has($changes, 'date')) {
            ProcessInvoiceTransactionTimeSeries::dispatch($invoiceTransaction, Carbon::parse($oldDate)->toDateString())->delay($this->hydratorsDelay);
        }

        ProcessInvoiceTransactionTimeSeries::dispatch($invoiceTransaction, Carbon::parse($invoiceTransaction->date)->toDateString())->delay($this->hydratorsDelay);

        return $invoiceTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_ordered'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_bonus'      => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_fail'       => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_cancelled'  => ['sometimes', 'sometimes', 'numeric', 'min:0'],
            'gross_amount'        => ['sometimes', 'required', 'numeric'],
            'net_amount'          => ['sometimes', 'required', 'numeric'],
            'org_exchange'        => ['sometimes', 'numeric'],
            'grp_exchange'        => ['sometimes', 'numeric'],
            'org_net_amount'      => ['sometimes', 'numeric'],
            'grp_net_amount'      => ['sometimes', 'numeric'],
            'tax_category_id'     => ['sometimes', 'required', 'exists:tax_categories,id'],
            'date'                => ['sometimes', 'required', 'date'],
            'submitted_at'        => ['sometimes', 'required', 'date'],
        ];
        if (!$this->strict) {
            $rules['model_type']        = ['sometimes', 'required', 'string'];
            $rules['model_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['asset_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['historic_asset_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['order_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['transaction_id']    = ['sometimes', 'nullable', 'integer'];

            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(InvoiceTransaction $invoiceTransaction, array $modelData, int $hydratorsDelay = 1800, bool $strict = true): InvoiceTransaction
    {
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoiceTransaction->shop, $modelData);

        return $this->handle($invoiceTransaction, $this->validatedData);
    }
}
