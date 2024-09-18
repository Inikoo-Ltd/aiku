<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToInQueue extends OrgAction
{
    use WithActionUpdate;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'in_queue_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::IN_QUEUE->value);

        return $this->update($deliveryNote, $modelData);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }
}
