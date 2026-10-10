<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Lorisleiva\Actions\ActionRequest;

trait WithDeliveryNoteShipmentAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->route()?->getControllerClass() !== static::class) {
            return true;
        }

        $deliveryNote = $request->route('deliveryNote')
            ?? $request->route('deliveryNoteItem')?->deliveryNote
            ?? $request->route('picking')?->deliveryNote;
        if ($deliveryNote === null && $request->route('deliveryNoteLeaflet') instanceof DeliveryNoteLeaflet) {
            $deliveryNote = $request->route('deliveryNoteLeaflet')->deliveryNote;
        }

        return $deliveryNote instanceof DeliveryNote && $deliveryNote->canBeShippedBy($request->user());
    }
}
