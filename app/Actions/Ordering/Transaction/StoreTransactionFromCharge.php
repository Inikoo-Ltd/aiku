<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 23:31:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrdersStats;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithOrderExchanges;
use App\Actions\Traits\WithStoreNoProductTransaction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionFromCharge extends OrgAction
{
    use WithOrderExchanges;
    use WithNoProductStoreTransaction;
    use WithStoreNoProductTransaction;


    private ?Charge $charge;

    public function handle(Order $order, ?Charge $charge, array $modelData): Transaction
    {
        $modelData = $this->prepareChargeTransaction($charge, $modelData);
        $modelData = $this->transactionFieldProcess($order, $modelData);

        /** @var Transaction $transaction */
        $transaction = $order->transactions()->create($modelData);


        if ($this->strict) {
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
            AssetHydrateOrdersStats::dispatch($transaction->asset_id)->delay($this->hydratorsDelay);
        }

        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'state'            => ['sometimes', Rule::enum(TransactionStateEnum::class)],
            'status'           => ['sometimes', Rule::enum(TransactionStatusEnum::class)],
            'gross_amount'     => ['sometimes', 'numeric'],
            'net_amount'       => ['sometimes', 'numeric'],
            'org_exchange'     => ['sometimes', 'numeric'],
            'grp_exchange'     => ['sometimes', 'numeric'],
            'org_net_amount'   => ['sometimes', 'numeric'],
            'grp_net_amount'   => ['sometimes', 'numeric'],

            'tax_category_id' => ['sometimes', 'required', 'exists:tax_categories,id'],
            'date'            => ['sometimes', 'required', 'date'],
            'quantity_ordered'    => ['required', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'required', 'numeric', 'min:0'],
            'submitted_at'        => ['sometimes', 'required', 'date'],

        ];

        if (!$this->strict) {
            $rules['source_alt_id'] = ['sometimes', 'string', 'max:255'];
            $rules['fetched_at']    = ['sometimes', 'required', 'date'];
            $rules['created_at']    = ['sometimes', 'required', 'date'];
        }


        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {

        if ($this->charge && $this->charge->shop_id != $this->shop->id) {
            $validator->errors()->add('charge', 'Charge does not belong to this shop');
        }

    }


    public function action(Order $order, ?Charge $charge, array $modelData, bool $strict = true): Transaction
    {
        $this->strict = $strict;
        $this->charge = $charge;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $charge, $this->validatedData);
    }


}
