<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Apr 2024 13:00:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class RecurringBillHydrateTransactions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(RecurringBill $recurringBill): string
    {
        return $recurringBill->id;
    }

    public function handle(RecurringBill $recurringBill): void
    {
        $stats = [
            'number_transactions'                   => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->count(),
            'number_transactions_type_pallets'      => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->where('item_type', 'Pallet')->count(),
            'number_transactions_type_stored_items' => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->where('item_type', 'StoreItem')->count(),
            'number_transactions_type_services'     => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->where('item_type', 'Service')->count(),
            'number_transactions_type_products'     => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->where('item_type', 'Product')->count(),
            'number_transactions_type_spaces'       => RecurringBillTransaction::where('recurring_bill_id', $recurringBill->id)->where('item_type', 'Space')->count()

        ];


        $recurringBill->stats()->update($stats);
    }
}
