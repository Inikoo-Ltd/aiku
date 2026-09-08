<?php

/*
 * Author Louis Perez
 * Created on 28-07-2026-09h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Ordering\Transaction;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class RemoveTransactionDiscount extends OrgAction
{
    use WithActionUpdate;


    public function handle(Transaction $transaction): Transaction
    {
        return UpdateTransactionDiscretionaryDiscount::make()->action($transaction, [
            'discretionary_offer'       => 0,
            'discretionary_offer_label' => 'Discount Removal'
        ]);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction);
    }
}
