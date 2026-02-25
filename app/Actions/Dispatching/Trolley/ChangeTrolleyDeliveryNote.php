<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 16:10:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ChangeTrolleyDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): DeliveryNote
    {
        $trolley = null;
        if (Arr::has($modelData, 'trolley') && $modelData['trolley']) {
            $trolley = Trolley::find($modelData['trolley']);
        }

        if ($trolley) {
            SyncDeliveryNoteTrolleys::run($deliveryNote, [
                'trolleys' => [
                    $trolley->id
                ]
            ]);
        }

        return $deliveryNote;
    }


    public function rules(): array
    {
        return [
            'trolley' => [
                'required',
                'nullable',
                'integer',
                Rule::exists('trolleys', 'id')->where('organisation_id', $this->organisation->id)
            ],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $this->validatedData);
    }


}
