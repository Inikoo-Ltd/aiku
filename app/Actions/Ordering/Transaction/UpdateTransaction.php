<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionFailStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        if (Arr::exists($modelData, 'quantity_ordered') && $this->strict) {
            if ($modelData['quantity_ordered'] == 0 && $transaction->order->status == OrderStatusEnum::CREATING) {
                return DeleteTransaction::run($transaction);
            }

            $historicAsset = $transaction->historicAsset;
            $net           = $historicAsset->price * Arr::get($modelData, 'quantity_ordered');
            // here we are going to  deal with discounts 15/09/24
            $gross = $historicAsset->price * Arr::get($modelData, 'quantity_ordered');

            data_set($modelData, 'gross_amount', $gross);
            data_set($modelData, 'net_amount', $net);
        }

        $this->update($transaction, $modelData, ['data']);

        if ($this->strict) {
            $changes = Arr::except($transaction->getChanges(), ['updated_at', 'last_fetched_at']);
            if (count($changes) && (array_key_exists('net_amount', $changes) || array_key_exists('gross_amount', $changes))) {
                $transaction->order->refresh();
                CalculateOrderTotalAmounts::run($transaction->order);
            }
        }

        return $transaction;
    }

    public function rules(): array
    {
        $qtyRule     = ['sometimes', 'numeric', 'min:0'];
        $numericRule = ['sometimes', 'numeric'];

        $rules = [
            'quantity_ordered'    => $qtyRule,
            'quantity_bonus'      => $qtyRule,
            'quantity_dispatched' => $qtyRule,
            'quantity_fail'       => $qtyRule,
            'quantity_cancelled'  => ['sometimes', 'sometimes', 'numeric', 'min:0'],
            'state'               => ['sometimes', Rule::enum(TransactionStateEnum::class)],
            'status'              => ['sometimes', Rule::enum(TransactionStatusEnum::class)],
            'fail_status'         => ['sometimes', 'nullable', Rule::enum(TransactionFailStatusEnum::class)],
            'gross_amount'        => $numericRule,
            'net_amount'          => $numericRule,
            'org_exchange'        => $numericRule,
            'grp_exchange'        => $numericRule,
            'org_net_amount'      => $numericRule,
            'grp_net_amount'      => $numericRule,
            'tax_category_id'     => ['sometimes', 'exists:tax_categories,id'],
            'date'                => ['sometimes', 'date'],
            'submitted_at'        => ['sometimes', 'date'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);

            $rules['model_type']        = ['sometimes', 'required', 'string'];
            $rules['model_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['asset_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['historic_asset_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['in_warehouse_at']   = ['sometimes', 'required', 'date'];
            $rules                      = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Transaction $transaction, array $modelData, bool $strict = true): Transaction
    {
        $this->strict = $strict;
        $this->initialisationFromShop($transaction->shop, $modelData);

        return $this->handle($transaction, $this->validatedData);
    }

    public function asController(Order $order, Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
