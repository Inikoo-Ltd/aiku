<?php

/*
 * Author: Vika Aqordi
 * Created on 04-02-2026-14h-30m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNoteItem\CalculateDeliveryNoteItemTotalPicked;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class PickFromMagicPlace extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private DeliveryNoteItem $deliveryNoteItem;
    protected User $user;

    public function handle(DeliveryNoteItem $deliveryNoteItem): Picking
    {
        dd('magic place');
        // return $picking;
    }

    // public function rules(): array
    // {
    //     return [
    //         'not_picked_reason' => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
    //         'not_picked_note'   => ['sometimes', 'string'],
    //         'quantity'          => ['sometimes', 'numeric'],
    //         'picker_user_id'    => [
    //             'required',
    //             Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
    //         ],
    //     ];
    // }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    // public function prepareForValidation(ActionRequest $request): void
    // {
    //     if (!$request->has('picker_user_id')) {
    //         $this->set('picker_user_id', $this->user->id);
    //     }
    //     if (!$request->has('quantity')) {
    //         $this->set('quantity', $this->deliveryNoteItem->quantity_required - $this->deliveryNoteItem->quantity_picked);
    //     }
    // }

    // public function asController(DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): Picking
    // {
    //     $this->user             = $request->user();
    //     $this->deliveryNoteItem = $deliveryNoteItem;
    //     $this->initialisationFromShop($deliveryNoteItem->shop, $request);

    //     return $this->handle($deliveryNoteItem, $this->validatedData);
    // }

    // public function action(DeliveryNoteItem $deliveryNoteItem, User $user, array $modelData): Picking
    // {
    //     $this->asAction         = true;
    //     $this->user             = $user;
    //     $this->deliveryNoteItem = $deliveryNoteItem;

    //     $this->initialisationFromShop($deliveryNoteItem->shop, $modelData);

    //     return $this->handle($deliveryNoteItem, $this->validatedData);
    // }


}
