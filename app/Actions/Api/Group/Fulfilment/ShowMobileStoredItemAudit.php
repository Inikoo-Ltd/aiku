<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowMobileStoredItemAudit
{
    use AsController;
    use WithMobileStoredItemAudits;

    public function asController(Warehouse $warehouse, StoredItemAudit $storedItemAudit, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        $pallet = $this->palletOfAudit($warehouse, $storedItemAudit);

        return response()->json(['data' => $this->auditDetail($storedItemAudit, $pallet)]);
    }
}
