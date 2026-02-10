<?php

/*
 * Author: Vika Aqordi
 * Created on 30-01-2026-15h-33m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Trolley\AttachDeliveryNoteToTrolley;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StartHandlingWithTrolleyDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, User $user, array $modelData): DeliveryNote
    {
        $trolley = null;
        if (Arr::has($modelData, 'trolley')) {
            $trolley = Trolley::find($modelData['trolley']);
        }

        if ($trolley) {
            AttachDeliveryNoteToTrolley::run($trolley, $deliveryNote);
        }
        StartHandlingDeliveryNote::run($deliveryNote, $user);

        return $deliveryNote;
    }


    public function rules(): array
    {
        return [
            'trolley' => [
                'nullable',
                'integer',
                Rule::exists('trolleys', 'id')->where('organisation_id', $this->organisation->id)
            ],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);
        $this->handle($deliveryNote, $request->user(), $this->validatedData);
    }


}
