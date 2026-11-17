<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\StoredItemAuditDelta\DeleteStoredItemAuditDelta;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobileStoredItemAuditDelta
{
    use AsController;
    use WithMobileStoredItemAudits;

    public function asController(Warehouse $warehouse, StoredItemAudit $storedItemAudit, StoredItemAuditDelta $storedItemAuditDelta, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        $pallet = $this->palletOfAudit($warehouse, $storedItemAudit);
        abort_unless($storedItemAuditDelta->stored_item_audit_id === $storedItemAudit->id, 404);

        $this->changeAudit($storedItemAudit, function () use ($storedItemAuditDelta) {
            $delta = StoredItemAuditDelta::find($storedItemAuditDelta->id);
            if ($delta) {
                DeleteStoredItemAuditDelta::run($delta);
            }
        });

        return $this->respondWithAudit($storedItemAudit, $pallet, 'done', __('Count removed.'));
    }
}
