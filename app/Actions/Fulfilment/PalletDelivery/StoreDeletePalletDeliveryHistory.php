<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 18:05:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletDelivery;

use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsObject;
use OwenIt\Auditing\Events\AuditCustom;

class StoreDeletePalletDeliveryHistory
{
    use asObject;

    public function handle(PalletDelivery $palletDelivery, RecurringBill|Customer $model): void
    {
        $model->auditEvent     = 'delete';
        $model->isCustomEvent  = true;
        $model->auditCustomOld = [
            'delivery' => $palletDelivery->reference
        ];
        $model->auditCustomNew = [
            'delivery' => __("Pallet delivery :ref has been deleted.", ['ref' => $palletDelivery->reference])
        ];
        Event::dispatch(new AuditCustom($model));
    }

}
