<?php

/*
 * Author: Vika Aqordi
 * Created on 30-01-2026-15h-39m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class SetPackedWithPickingBaysDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $pickingBay = data_get($modelData, 'picked_bay');
        dd("maybe can copy from SetDeliveryNoteStateAsPacked. selected picking bay: $pickingBay");
    }


    public function rules(): array
    {
        return [
            'picked_bay' => ['required', 'string'],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }

}
