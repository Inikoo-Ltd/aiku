<?php

/*
 * Author: Vika Aqordi
 * Created on 19-02-2026-16h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\Trolley\AttachTrolleyToDeliveryNote;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Trolley;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ChangeTrolleyDeliveryNote extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, User $user, array $modelData): DeliveryNote
    {
        dd($modelData);

        // Code below will result add trolley, instead of replace trollley
        $trolley = null;
        if (Arr::has($modelData, 'trolley') && $modelData['trolley']) {
            $trolley = Trolley::find($modelData['trolley']);
        }

        if ($trolley) {
            AttachTrolleyToDeliveryNote::run($trolley, $deliveryNote);
        }

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
