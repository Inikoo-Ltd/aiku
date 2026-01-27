<?php

/*
 * Author: Vika Aqordi
 * Created on 15-01-2026-15h-54m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Ordering\Transaction;

use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
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

        //        if (Arr::get($modelData, 'discretionary_offer') == 0) {
        //            $modelData['discretionary_offer'] = null;
        //        }
        //
        //        $transaction->update($modelData);
        //        OrderHydrateDiscretionaryOffersData::run($transaction->order);
        //        CalculateOrderDiscounts::run($transaction->order);


        return $transaction;
    }

    public function rules(): array
    {
        return [
            'amount'       => [ 'numeric', 'min:0'],
        ];
    }


    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->transaction = $transaction;
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
