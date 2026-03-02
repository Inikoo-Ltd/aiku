<?php

/*
 * Author: Vika Aqordi
 * Created on 26-02-2026-09h-57m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Dispatching\PickedBay\AttachDeliveryNoteToPickedBay;
use App\Actions\Dispatching\PickedBay\Hydrators\PickedBayHydrateNumberDeliveryNotes;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ChangePickingBaysDeliveryNote extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        return DB::transaction(function () use ($deliveryNote, $modelData) {
            foreach ($deliveryNote->pickedBays as $pickedBay) {
                DB::table('picked_bay_has_delivery_notes')
                    ->where('delivery_note_id', $deliveryNote->id)->where('picked_bay_id', $pickedBay->id)->delete();
                PickedBayHydrateNumberDeliveryNotes::run($pickedBay->id);
            }

            $pickedBay = null;
            if (Arr::has($modelData, 'picked_bay') && $modelData['picked_bay']) {
                $pickedBay = PickedBay::find($modelData['picked_bay']);
            }
            if ($pickedBay) {
                AttachDeliveryNoteToPickedBay::run($pickedBay, $deliveryNote);
            }

            return $deliveryNote;
        });
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

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }

}
