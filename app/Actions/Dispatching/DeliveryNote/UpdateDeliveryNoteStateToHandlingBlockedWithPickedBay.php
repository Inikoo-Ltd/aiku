<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Feb 2026 12:30:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteStateToHandlingBlockedWithPickedBay extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return UpdateDeliveryNoteStateToHandlingBlocked::make()->action($deliveryNote);
    }


    public function rules(): array
    {
        return [
            'picked_bay' => [
                'required',
                'integer',
                Rule::exists('picked_bays', 'id')->where('organisation_id', $this->organisation->id)
            ],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        dd($request->all());
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }


}
