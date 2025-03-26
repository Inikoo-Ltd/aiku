<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:32 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
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

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        if (Arr::exists($modelData, 'quantity_ordered')) {
            if ($this->strict) {
                $historicAsset = $transaction->historicAsset;
                $net = $historicAsset->price * Arr::get($modelData, 'quantity_ordered');
                // todo deal with discounts
                $gross = $historicAsset->price * Arr::get($modelData, 'quantity_ordered');

                data_set($modelData, 'gross_amount', $gross);
                data_set($modelData, 'net_amount', $net);

            }

        }

        $this->update($transaction, $modelData, ['data']);

        if ($this->strict) {
            $changes = Arr::except($transaction->getChanges(), ['updated_at', 'last_fetched_at']);
            if (count($changes)) {
                if (array_key_exists('net_amount', $changes) || array_key_exists('gross_amount', $changes)) {
                    $transaction->order->refresh();
                    CalculateOrderTotalAmounts::run($transaction->order);
                }
            }
        }
        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered'    => ['sometimes', 'numeric', 'min:0'],
            'quantity_bonus'      => ['sometimes', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'numeric', 'min:0'],
            'quantity_fail'       => ['sometimes', 'numeric', 'min:0'],
            'quantity_cancelled'  => ['sometimes', 'sometimes', 'numeric', 'min:0'],
            'source_id'           => ['sometimes', 'string'],
            'state'               => ['sometimes', Rule::enum(TransactionStateEnum::class)],
            'status'              => ['sometimes', Rule::enum(TransactionStatusEnum::class)],
            'fail_status'         => ['sometimes', 'nullable', Rule::enum(TransactionFailStatusEnum::class)],
            'gross_amount'        => ['sometimes', 'numeric'],
            'net_amount'          => ['sometimes', 'numeric'],
            'org_exchange'        => ['sometimes', 'numeric'],
            'grp_exchange'        => ['sometimes', 'numeric'],
            'org_net_amount'      => ['sometimes', 'numeric'],
            'grp_net_amount'      => ['sometimes', 'numeric'],
            'tax_category_id'     => ['sometimes', 'exists:tax_categories,id'],
            'date'                => ['sometimes', 'date'],
            'submitted_at'        => ['sometimes', 'date'],
        ];

        if (!$this->strict) {
            $rules['model_type']        = ['sometimes', 'required', 'string'];
            $rules['model_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['asset_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['historic_asset_id'] = ['sometimes', 'nullable', 'integer'];

            $rules['in_warehouse_at'] = ['sometimes', 'required', 'date'];
            $rules['created_at']      = ['sometimes', 'required', 'date'];
            $rules['last_fetched_at'] = ['sometimes', 'required', 'date'];
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
