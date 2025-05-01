<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Transaction\TransactionFailStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class RetinaEcomUpdateTransaction extends RetinaAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        return UpdateTransaction::run($transaction, $modelData);
    }


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
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

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisation($request);

        return $this->handle($transaction, $this->validatedData);
    }
}
