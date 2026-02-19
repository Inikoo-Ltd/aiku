<?php

/*
 * Author: Vika Aqordi
 * Created on 30-01-2026-15h-39m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Trolley\AttachTrolleyToDeliveryNote;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetPackedWithPickingBaysDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $pickedBay = null;
        if (Arr::has($modelData, 'picked_bay') && $modelData['picked_bay']) {
            $pickedBay = PickedBay::find($modelData['picked_bay']);
        }
        if ($pickedBay) {
            AttachTrolleyToDeliveryNote::run($trolley, $deliveryNote);
        }

        dd("maybe can copy from SetDeliveryNoteStateAsPacked. selected picking bay id: $pickingBay");
    }


    public function rules(): array
    {
        return [
            'picked_bay' => [
                'nullable',
                'integer',
                Rule::exists('picked_bays', 'id')->where('organisation_id', $this->organisation->id)
            ],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }

}
