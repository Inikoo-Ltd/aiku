<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 18:05:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Models\CRM\Customer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

class StoreDeletePalletHistory
{
    use asObject;

    public function handle(Pallet $pallet, RecurringBill|Customer $model): void
    {
        $model->auditEvent                    = 'delete';
        $model->isCustomEvent                 = true;
        $model->auditCustomOld = [
            'pallet' => $pallet->reference
        ];
        $model->auditCustomNew = [
            'pallet' => __("The pallet :ref has been deleted.", ['ref' => $pallet->reference])
        ];
        Event::dispatch(AuditCustom::class, [
            $model
        ]);
    }

}
