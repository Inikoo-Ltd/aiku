<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileShippers
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @return array{data: array<int, array<string, mixed>>, directive: array<string, mixed>}
     */
    public function handle(DeliveryNote $deliveryNote): array
    {
        $showDeliveryNote = ShowDeliveryNote::make();
        $directive        = $showDeliveryNote->getShipperDirective($deliveryNote);
        $canOverrideLock  = $showDeliveryNote->canOverrideShipperLock($deliveryNote);

        $shippers = Shipper::where('organisation_id', $deliveryNote->organisation_id)
            ->where('status', true)
            ->orderBy('code')
            ->get()
            ->reject(fn (Shipper $shipper) => $shipper->isSupervisorOnly() && !$canOverrideLock)
            ->map(fn (Shipper $shipper) => [
                'id'             => $shipper->id,
                'code'           => $shipper->code,
                'name'           => $shipper->name,
                'trade_as'       => $shipper->trade_as,
                'api_shipper'    => $shipper->api_shipper,
                'type'           => $shipper->api_shipper ? 'api' : 'manual',
                'needs_tracking' => !$shipper->api_shipper && $directive['tracking_channel'] !== null,
                'is_locked_out'  => $directive['locked_shipper_id']
                    && $directive['locked_shipper_id'] != $shipper->id
                    && ($directive['locked_by'] === 'customer' || !$canOverrideLock),
            ])
            ->values()
            ->all();

        return [
            'data'      => $shippers,
            'directive' => [
                'locked_shipper_id'    => $directive['locked_shipper_id'],
                'preferred_shipper_id' => $directive['preferred_shipper_id'],
                'locked_by'            => $directive['locked_by'],
                'can_override_lock'    => $canOverrideLock,
                'tracking_channel'     => $directive['tracking_channel'],
            ],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanView($deliveryNote, $user);
        abort_unless($deliveryNote->canBeShippedBy($user), 403, __('You can not add or change the shipments of this delivery note.'));

        return response()->json($this->handle($deliveryNote));
    }
}
