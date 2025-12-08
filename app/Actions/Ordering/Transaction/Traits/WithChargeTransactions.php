<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 14:00:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Transaction\Traits;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;

/**
 * Reusable helpers to create or update a charge transaction on an order.
 */
trait WithChargeTransactions
{
    /**
     * Create a transaction for a charge on the given order.
     */
    private function storeChargeTransaction(Order $order, Charge $charge, $chargeAmount): Transaction
    {
        return StoreTransaction::run(
            $order,
            $charge->historicAsset,
            [
                'quantity_ordered' => 1,
                'gross_amount' => $chargeAmount,
                'net_amount' => $chargeAmount,
            ],
            false
        );
    }

    /**
     * Update an existing transaction with the given charge/amount details.
     */
    private function updateChargeTransaction(Transaction $transaction, Charge $charge, $chargeAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id' => $charge->id,
                'asset_id' => $charge->asset_id,
                'historic_asset_id' => $charge->historicAsset->id,
                'gross_amount' => $chargeAmount ?? 0,
                'net_amount' => $chargeAmount ?? 0,
            ],
            false
        );
    }
}
