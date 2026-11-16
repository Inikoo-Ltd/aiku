<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use Illuminate\Validation\ValidationException;

trait WithMobileShipments
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function storableShipper(DeliveryNote $deliveryNote, int $shipperId, bool $overrideLock): Shipper
    {
        $showDeliveryNote = ShowDeliveryNote::make();

        /** @var Shipper|null $shipper */
        $shipper = Shipper::where('organisation_id', $deliveryNote->organisation_id)->where('status', true)->get()->firstWhere('id', $shipperId);

        if (!$shipper) {
            throw ValidationException::withMessages(['shipper_id' => __('Choose an active shipper of this organisation.')]);
        }

        $isSupervisor = $showDeliveryNote->canOverrideShipperLock($deliveryNote);

        if ($shipper->isSupervisorOnly() && !$isSupervisor) {
            throw ValidationException::withMessages(['shipper' => __('Only a dispatch supervisor can use :shipper.', ['shipper' => $shipper->name])]);
        }

        $directive = $showDeliveryNote->getShipperDirective($deliveryNote);

        if ($directive['locked_by'] === 'preferred_shipping'
            && $directive['locked_shipper_id']
            && $directive['locked_shipper_id'] != $shipper->id
            && $isSupervisor
            && !$overrideLock) {
            throw ValidationException::withMessages([
                'override_lock' => __('This delivery note is locked to :shipper. Confirm to use another shipper.', ['shipper' => $directive['preferred_shipper']['name'] ?? '']),
            ]);
        }

        return $shipper;
    }

    protected function shipmentAddedMessage(DeliveryNote $deliveryNote, DeliveryNoteStateEnum $stateBefore): string
    {
        return $stateBefore !== DeliveryNoteStateEnum::DISPATCHED && $deliveryNote->refresh()->state === DeliveryNoteStateEnum::DISPATCHED
            ? __('Shipment added. The delivery note was dispatched.')
            : __('Shipment added.');
    }
}
