<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\StoredItemAudit\UI\CreateStoredItemAuditFromPallet;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class StartMobilePalletStoredItemAudit
{
    use AsController;
    use WithMobileStoredItemAudits;

    public function asController(Warehouse $warehouse, Pallet $pallet, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        abort_unless($pallet->warehouse_id === $warehouse->id, 404);

        /** @var StoredItemAudit $storedItemAudit */
        $storedItemAudit = $this->underLock('pallet', $pallet->id, function () use ($pallet, $warehouse) {
            $pallet = Pallet::with('fulfilmentCustomer')->findOrFail($pallet->id);

            if ($pallet->status !== PalletStatusEnum::STORING) {
                $this->refuseState(__('Only a pallet in storage can be audited.'));
            }

            if (!$pallet->fulfilmentCustomer?->items_storage) {
                $this->refuseState(__('This customer does not store items on pallets.'));
            }

            return CreateStoredItemAuditFromPallet::make()->handle($pallet, ['warehouse_id' => $warehouse->id]);
        });

        return $this->respondWithAudit($storedItemAudit, $pallet, 'done', $storedItemAudit->wasRecentlyCreated ? __('Audit started.') : __('Audit resumed.'));
    }
}
