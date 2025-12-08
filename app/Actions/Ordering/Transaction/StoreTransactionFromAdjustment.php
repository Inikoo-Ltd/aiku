<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 18:47:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\WithOrderExchanges;
use App\Actions\Traits\WithStoreNoProductTransaction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Rule;

class StoreTransactionFromAdjustment extends OrgAction
{
    use WithNoProductStoreTransaction;
    use WithOrderExchanges;
    use WithStoreNoProductTransaction;

    public function handle(Order $order, Adjustment $adjustment, array $modelData): Transaction
    {
        $modelData = $this->prepareAdjustmentTransaction($adjustment, $modelData);
        $modelData = $this->transactionFieldProcess($order, $modelData);

        /** @var Transaction $transaction */
        $transaction = $order->transactions()->create($modelData);

        if ($this->strict) {
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
        }

        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'state' => ['sometimes', Rule::enum(TransactionStateEnum::class)],
            'status' => ['sometimes', Rule::enum(TransactionStatusEnum::class)],
            'gross_amount' => ['sometimes', 'numeric'],
            'net_amount' => ['sometimes', 'numeric'],
            'org_exchange' => ['sometimes', 'numeric'],
            'grp_exchange' => ['sometimes', 'numeric'],
            'org_net_amount' => ['sometimes', 'numeric'],
            'grp_net_amount' => ['sometimes', 'numeric'],

            'tax_category_id' => ['sometimes', 'required', 'exists:tax_categories,id'],
            'date' => ['sometimes', 'required', 'date'],
            'quantity_ordered' => ['required', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'required', 'numeric', 'min:0'],
            'submitted_at' => ['sometimes', 'required', 'date'],

        ];

        if (! $this->strict) {
            $rules['source_alt_id'] = ['sometimes', 'string', 'max:255'];
            $rules['fetched_at'] = ['sometimes', 'required', 'date'];
            $rules['created_at'] = ['sometimes', 'required', 'date'];
        }

        return $rules;
    }

    public function action(Order $order, Adjustment $adjustment, array $modelData, bool $strict = true): Transaction
    {
        $this->strict = $strict;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $adjustment, $this->validatedData);
    }
}
