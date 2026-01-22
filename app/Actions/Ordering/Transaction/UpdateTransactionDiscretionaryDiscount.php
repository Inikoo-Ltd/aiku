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
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class UpdateTransactionDiscretionaryDiscount extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Transaction $transaction;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        dd('xxxxxx', $modelData);

        return $transaction;
    }

    public function rules(): array
    {
        $rules = [
            'discretionary_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
        ];


        return $rules;
    }

    public function action(Transaction $transaction, array $modelData): Transaction
    {

        $this->initialisationFromShop($transaction->shop, $modelData);

        return $this->handle($transaction, $this->validatedData);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->transaction = $transaction;
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction, $this->validatedData);
    }
}
