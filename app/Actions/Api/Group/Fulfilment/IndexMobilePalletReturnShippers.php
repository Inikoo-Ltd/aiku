<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobilePalletReturnShippers
{
    use AsController;
    use WithMobilePalletReturns;

    public const string SHIPPER_TAKING_DELIVERY_NOTES_ONLY = 'ctt-es';

    /**
     * @return array{data: array<int, array<string, mixed>>, directive: array<string, mixed>}
     */
    public function handle(PalletReturn $palletReturn): array
    {
        return [
            'data'      => Shipper::where('organisation_id', $palletReturn->organisation_id)
                ->where('status', true)
                ->orderBy('code')
                ->get()
                ->reject(fn (Shipper $shipper) => $shipper->api_shipper === self::SHIPPER_TAKING_DELIVERY_NOTES_ONLY)
                ->map(fn (Shipper $shipper) => [
                    'id'             => $shipper->id,
                    'code'           => $shipper->code,
                    'name'           => $shipper->name,
                    'trade_as'       => $shipper->trade_as,
                    'api_shipper'    => $shipper->api_shipper,
                    'type'           => $shipper->api_shipper ? 'api' : 'manual',
                    'needs_tracking' => false,
                    'is_locked_out'  => false,
                ])
                ->values()
                ->all(),
            'directive' => [
                'locked_shipper_id'    => null,
                'preferred_shipper_id' => null,
                'locked_by'            => null,
                'can_override_lock'    => false,
                'tracking_channel'     => null,
            ],
        ];
    }

    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureShipmentCanBeAdded($palletReturn);

        return response()->json($this->handle($palletReturn));
    }
}
