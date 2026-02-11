<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 21:25:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncDeliveryNoteTrolleys extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;


    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $oldTrolleysIDs   = $deliveryNote->trolleys->pluck('id')->toArray();
        $trolleysToFreeUp = array_diff($oldTrolleysIDs, $modelData['trolleys']);

        $trolleys = [];
        foreach ($modelData['trolleys'] as $trolleyId) {
            $trolleys[$trolleyId] = [
                'group_id'        => $deliveryNote->group_id,
                'organisation_id' => $deliveryNote->organisation_id
            ];
            $trolley              = Trolley::find($trolleyId);
            UpdateTrolley::run($trolley, [
                'current_delivery_note_id' => $deliveryNote->id
            ]);
        }


        $deliveryNote->trolleys()->sync($trolleys);
        foreach ($trolleysToFreeUp as $trolleyId) {
            $trolley = Trolley::find($trolleyId);
            UpdateTrolley::run($trolley, [
                'current_delivery_note_id' => null
            ]);
        }


        return $deliveryNote;
    }

    public function rules(): array
    {
        return [
            'trolleys'   => ['required', 'array'],
            'trolleys.*' => [
                'required',
                'integer',
                Rule::exists('trolleys', 'id')->where(function ($query) {
                    $query->where('organisation_id', $this->organisation->id);
                })
            ]
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }


}
