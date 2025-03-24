<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomers;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoices;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSales;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithOrderExchanges;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class StoreInvoiceTransaction extends OrgAction
{
    use WithOrderExchanges;
    use WithNoStrictRules;

    public function handle(Invoice $invoice, Transaction|HistoricAsset $model, array $modelData): InvoiceTransaction
    {
        if (Arr::exists($modelData, 'pallet_id')) {
            $palletId = Arr::pull($modelData, 'pallet_id');
        } else {
            $palletId = Arr::pull($modelData, 'data.pallet_id');
        }
        if (Arr::exists($modelData, 'handle_date')) {
            $handlingDate = Arr::pull($modelData, 'handle_date');
        } else {
            $handlingDate = Arr::pull($modelData, 'data.date');
        }

        data_set($modelData, 'date', now(), overwrite: false);

        if ($model instanceof Transaction) {
            data_set($modelData, 'model_type', $model->model_type);
            data_set($modelData, 'model_id', $model->model_id);
        } else {
            data_set($modelData, 'model_type', $model->asset->model_type);
            data_set($modelData, 'model_id', $model->asset->model_id);
        }


        $modelData['shop_id']         = $invoice->shop_id;
        $modelData['customer_id']     = $invoice->customer_id;
        $modelData['group_id']        = $invoice->group_id;
        $modelData['organisation_id'] = $invoice->organisation_id;


        $modelData = $this->processExchanges($modelData, $invoice->shop);


        if ($model instanceof Transaction) {
            $modelData['transaction_id'] = $model->id;
            $modelData['order_id']       = $model->order_id;
            if ($this->strict) {
                $historicAsset = $model->historicAsset;
            } else {
                $historicAsset = $model->historicAsset()->withTrashed()->first();
            }
        } else {
            $historicAsset = $model;
        }

        $modelData['asset_id']          = $historicAsset->asset_id;
        $modelData['historic_asset_id'] = $historicAsset->id;

        if ($historicAsset->model_type == 'Product') {
            /** @var Product $product */
            $product = $historicAsset->model;

            $modelData['family_id']     = $product->family_id;
            $modelData['department_id'] = $product->department_id;
        } elseif ($historicAsset->model_type == 'Service') {
            if ($historicAsset->model->is_pallet_handling == true) {
                if ($palletId == null) {
                    ValidationException::withMessages([
                        'message' => [
                            'pallet_id' => 'Pallet ID is required when handling a pallet',
                        ]
                    ]);
                }
                if ($handlingDate == null) {
                    ValidationException::withMessages([
                        'message' => [
                            'handle_date' => 'Handling date is required when handling a pallet',
                        ]
                    ]);
                }
                data_set($modelData, 'data.pallet_id', $palletId);
                data_set($modelData, 'data.date', $handlingDate);
            }
        }

        /** @var InvoiceTransaction $invoiceTransaction */
        $invoiceTransaction = $invoice->invoiceTransactions()->create($modelData);

        if ($invoiceTransaction->order_id and $invoiceTransaction->transaction_id) {

            $invoiceTransaction->transaction->update([
                'invoice_id' => $invoice->id
            ]);
        }

        AssetHydrateSales::dispatch($invoiceTransaction->asset)->delay($this->hydratorsDelay);
        AssetHydrateInvoices::dispatch($invoiceTransaction->asset)->delay($this->hydratorsDelay);
        AssetHydrateInvoicedCustomers::dispatch($invoiceTransaction->asset)->delay($this->hydratorsDelay);

        return $invoiceTransaction;
    }

    public function rules(): array
    {
        $rules = [
            'date'            => ['sometimes', 'required', 'date'],
            'tax_category_id' => ['required', 'exists:tax_categories,id'],
            'quantity'        => ['required', 'numeric'],
            'gross_amount'    => ['required', 'numeric'],
            'net_amount'      => ['required', 'numeric'],
            'org_exchange'    => ['sometimes', 'numeric'],
            'grp_exchange'    => ['sometimes', 'numeric'],
            'in_process'      => ['sometimes', 'boolean'],
            'pallet_id'       => ['sometimes'],
            'handle_date'     => ['sometimes'],
            'data'            => ['sometimes', 'array'],
            'recurring_bill_transaction_id' => ['sometimes'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    public function action(Invoice $invoice, Transaction|HistoricAsset $model, array $modelData, int $hydratorsDelay = 0, bool $strict = true): InvoiceTransaction
    {
        $this->asAction = true;
        $this->strict   = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $model, $this->validatedData);
    }


}
