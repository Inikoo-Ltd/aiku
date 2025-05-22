<?php
/*
 * author Arya Permana - Kirin
 * created on 22-05-2025-13h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Picking;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingStateEnum;
use App\Enums\Dispatching\Picking\PickingEngineEnum;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePicking extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private Picking $picking;

    public function handle(Picking $picking, array $modelData): Picking
    {
        if(Arr::get($modelData, 'quantity_picked') + $picking->quantity_picked == $picking->quantity_required) {
            data_set($modelData, 'state', PickingStateEnum::DONE);
        }

        $picking = $this->update($picking, $modelData);

        return $picking;
    }

    public function rules(): array
    {
        return [
            'state'              => ['sometimes', Rule::enum(PickingStateEnum::class)],
            'not_picked_reason'  => ['sometimes', Rule::enum(PickingNotPickedReasonEnum::class)],
            'not_picked_note'    => ['sometimes', 'string'],
            'location_id'     => [
                'sometimes',
                Rule::Exists('locations', 'id')->where('warehouse_id', $this->picking->deliveryNoteItem->deliveryNote->warehouse_id)
            ],
            'quantity_picked' => ['sometimes', 'numeric'],
            'picker_id'       => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->shop->group_id)
            ],
        ];
    }

    public function asController(Picking $picking, ActionRequest $request): Picking
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $request);

        return $this->handle($picking, $this->validatedData);
    }

    public function action(Picking $picking, array $modelData): Picking
    {
        $this->picking = $picking;
        $this->initialisationFromShop($picking->shop, $modelData);

        return $this->handle($picking, $this->validatedData);
    }
}
