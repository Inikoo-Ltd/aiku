<?php

/*
 * author Arya Permana - Kirin
 * created on 23-05-2025-14h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StartHandlingDeliveryNote extends OrgAction
{
    use WithActionUpdate;

    protected User $user;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        data_set($modelData, 'handling_at', now());
        data_set($modelData, 'state', DeliveryNoteStateEnum::HANDLING->value);

        if ($this->user->id != $deliveryNote->picker_user_id) {
            data_set($modelData, 'picker_user_id', $this->user->id);
        }

        foreach ($deliveryNote->deliveryNoteItems as $item) {
            UpdateDeliveryNoteItem::make()->action($item, [
                'state' => DeliveryNoteItemStateEnum::HANDLING
            ]);
        }

        return $this->update($deliveryNote, $modelData);
    }

    // public function prepareForValidation()
    // {
    //     if (!$this->asAction) {
    //         $employee = $this->user->employees()->first();
    //         if ($employee) {
    //             $pickerEmployee = $employee->jobPositions()->where('name', 'Picker')->first();
    //             if (!$pickerEmployee) {
    //                 throw ValidationException::withMessages([
    //                     'messages' => __('You cannot start handling this delivery note. You Are Not A Picker')
    //                 ]);
    //             }
    //         } elseif (!$employee) {
    //             throw ValidationException::withMessages([
    //                 'messages' => __('You Are Not An Employee')
    //             ]);
    //         }
    //     }
    // }


    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->user = $request->user();
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote);
    }

    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->user = $user;
        $this->asAction = true;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
