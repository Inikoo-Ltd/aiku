<?php

/*
 * author Arya Permana - Kirin
 * created on 10-04-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn;

use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

class StoreDeletePalletReturnHistory
{
    use asObject;

    public function handle(PalletReturn $palletReturn, RecurringBill|Customer $model): void
    {
        $model->auditEvent     = 'delete';
        $model->isCustomEvent  = true;
        $model->auditCustomOld = [
            'return' => $palletReturn->reference
        ];
        $model->auditCustomNew = [
            'return' => __("Pallet return :ref has been deleted.", ['ref' => $palletReturn->reference])
        ];
        Event::dispatch(new AuditCustom($model));
    }

}
