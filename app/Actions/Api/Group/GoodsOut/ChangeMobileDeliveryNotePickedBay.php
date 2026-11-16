<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\PickedBay\ChangePickingBaysDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickedBay;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ChangeMobileDeliveryNotePickedBay
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::HANDLING_BLOCKED, DeliveryNoteStateEnum::PICKED]);
        $this->ensureCanHandle($deliveryNote);

        $data = Validator::make($request->all(), [
            'picked_bay_id' => ['present', 'nullable', 'integer'],
        ])->validate();

        $pickedBay = null;
        if (!empty($data['picked_bay_id'])) {
            $pickedBay = PickedBay::find($data['picked_bay_id']);
            abort_unless($pickedBay && $pickedBay->warehouse_id === $deliveryNote->warehouse_id, 404);
        }

        $before = $this->noteSnapshot($deliveryNote);
        $this->passingWebRefusals(fn () => ChangePickingBaysDeliveryNote::run($deliveryNote, ['picked_bay' => $pickedBay?->id]), 'picked_bay_id');

        return $this->respond($deliveryNote, $user, $before, $this->noteSnapshot($deliveryNote), __('Picked bay changed.'), __('The picked bay did not change.'));
    }
}
