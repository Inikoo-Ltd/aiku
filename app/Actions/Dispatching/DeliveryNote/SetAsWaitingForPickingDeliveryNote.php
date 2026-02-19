<?php

/*
 * Author: Vika Aqordi
 * Created on 18-02-2026-16h-44m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class SetAsWaitingForPickingDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $pickingBay = data_get($modelData, 'picked_bay');
        dd("CCCCCCCCCCCCCCCCCCCCCCCCCC. selected picking bay id: $pickingBay");
    }


    public function rules(): array
    {
        return [
            'picked_bay' => ['required', 'numeric'],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }

}
