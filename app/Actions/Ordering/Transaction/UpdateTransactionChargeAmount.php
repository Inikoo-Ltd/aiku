<?php

/*
 * Author: Vika Aqordi
 * Created on 15-01-2026-15h-54m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Ordering\Transaction;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionChargeAmount extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Transaction $transaction;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        if (in_array($transaction->order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED,
        ])) {
            abort(403);
        }

        /** @var \App\Models\Billables\Charge $charge */
        $charge = $transaction->model;

        $offersData = [];

        if ($charge->type == ChargeTypeEnum::DISCRETIONARY->value) {
            $dataToUpdate = [
                'gross_amount' => $modelData['amount'],
                'net_amount'   => $modelData['amount']
            ];
        } else {
            $dataToUpdate = [
                'net_amount' => $modelData['amount']
            ];
            if ($transaction->gross_amount > 0 && $transaction->gross_amount > $modelData['amount']) {
                $discountFactor = $modelData['amount'] / $transaction->gross_amount;

                $offersData = [
                    'v' => 1,
                    'o' => [
                        't'  => 'percentage_off',
                        'pf' => $discountFactor,
                        'p'  => percentage($discountFactor, 1),
                        'l'  => '',
                    ]
                ];
            }
        }
        $dataToUpdate['offers_data'] = $offersData;

        UpdateTransaction::run($transaction, $dataToUpdate);


        return $transaction;
    }

    public function rules(): array
    {
        return [
            'amount' => ['numeric', 'min:0'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if (!$this->transaction->model instanceof Charge) {
            $validator->errors()->add('message', __('Transaction must be a charge'));
        }
    }


    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->transaction = $transaction;
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
