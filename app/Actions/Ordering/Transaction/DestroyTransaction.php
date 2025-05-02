<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:00:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrders;
use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Order\Search\OrderRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class DestroyTransaction extends OrgAction
{
    use WithActionUpdate;


    public function handle(Transaction $transaction): Transaction
    {
        $transaction->forceDelete();

        $order = $transaction->order;

        if ($this->strict) {
            CalculateOrderTotalAmounts::run($order);
            OrderHydrateTransactions::dispatch($order);
        }

        AssetHydrateOrders::dispatch($transaction->asset)->delay($this->hydratorsDelay);


        OrderRecordSearch::dispatch($order);

        return $transaction;
    }

    public function action(Transaction $transaction): Transaction
    {
        $this->initialisationFromShop($transaction->shop, []);

        return $this->handle($transaction);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisationFromShop($transaction->shop, $request);

        return $this->handle($transaction);
    }
}
