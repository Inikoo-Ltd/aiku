<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 11:41:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Catalogue\Shop\Hydrators\HasDeliveryNoteHydrators;
use App\Actions\Ordering\Order\UpdateState\UpdateOrderStateToPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;

class UpdateDeliveryNoteStateToPicked extends OrgAction
{
    use WithActionUpdate;
    use HasDeliveryNoteHydrators;


    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $oldState = $deliveryNote->state;

        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            data_set($modelData, 'picked_at', now());
            data_set($modelData, 'state', DeliveryNoteStateEnum::PICKED->value);

            if ($deliveryNote->type != DeliveryNoteTypeEnum::REPLACEMENT) {
                UpdateOrderStateToPicked::make()->action($deliveryNote->orders->first(), true);
            }

            return $this->update($deliveryNote, $modelData);
        });

        $this->deliveryNoteHandlingHydrators($deliveryNote, $oldState);
        $this->deliveryNoteHandlingHydrators($deliveryNote, DeliveryNoteStateEnum::PICKED);

        return $deliveryNote;
    }


}
