<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-09h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\Pallet\SetPalletAsDamaged;
use App\Actions\Fulfilment\Pallet\SetPalletAsLost;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StoreMobilePalletIncident
{
    use AsController;
    use WithMobileStoredPallets;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanChangePallet($user, $warehouse, $pallet, self::canEditFulfilmentWarehouse($user, $warehouse), __('You can not report pallet incidents in this warehouse.'));

        $data = Validator::make($request->all(), [
            'state'   => ['required', Rule::in([PalletStateEnum::DAMAGED->value, PalletStateEnum::LOST->value])],
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ])->validate();

        $isDamaged = $data['state'] === PalletStateEnum::DAMAGED->value;

        $this->underFulfilmentLock('pallet', $pallet->id, function () use ($pallet, $data, $user, $isDamaged) {
            $pallet->refresh();

            if ($pallet->status !== PalletStatusEnum::STORING) {
                $this->refuseState(__('Only a stored pallet can be set as damaged or lost.'));
            }

            $incident = $isDamaged ? SetPalletAsDamaged::make() : SetPalletAsLost::make();
            $incident->action($pallet, [
                'message'     => $data['message'] ?? null,
                'reporter_id' => $user->id,
            ]);
        });

        return $this->respondWithPallet($warehouse, $pallet, $user, 'done', $isDamaged ? __('Pallet set as damaged.') : __('Pallet set as lost.'));
    }
}
