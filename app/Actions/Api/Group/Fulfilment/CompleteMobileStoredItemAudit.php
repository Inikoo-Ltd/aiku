<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\StoredItemAudit\CompleteStoredItemAudit;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class CompleteMobileStoredItemAudit
{
    use AsController;
    use WithMobileStoredItemAudits;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, StoredItemAudit $storedItemAudit, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        $pallet = $this->palletOfAudit($warehouse, $storedItemAudit);

        $this->changeAudit($storedItemAudit, fn () => DB::transaction(function () use ($storedItemAudit) {
            $lockedAudit = StoredItemAudit::whereKey($storedItemAudit->id)->lockForUpdate()->firstOrFail();

            if ($lockedAudit->state !== StoredItemAuditStateEnum::IN_PROCESS) {
                $this->refuseState(__('This audit is already completed.'));
            }

            if (!$lockedAudit->deltas()->exists()) {
                $this->refuseState(__('Count at least one item before completing the audit.'));
            }

            CompleteStoredItemAudit::make()->action($lockedAudit, []);
        }));

        return $this->respondWithAudit($storedItemAudit, $pallet, 'done', __('Audit completed.'));
    }
}
