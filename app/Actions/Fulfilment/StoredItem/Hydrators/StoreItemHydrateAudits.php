<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Feb 2025 10:25:58 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\StoredItemAuditDelta\StoredItemAuditDeltaStateEnum;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class StoreItemHydrateAudits implements ShouldBeUnique
{
    use WithActionUpdate;
    use WithEnumStats;

    public function getJobUniqueId(StoredItem $storedItem): string
    {
        return $storedItem->id;
    }

    public function handle(StoredItem $storedItem): void
    {
        $lastAuditAt = null;
        $lastAuditId      = null;
        $lastAuditDeltaId = null;
        if ($latestAuditDelta = DB::table('stored_item_audit_deltas')
            ->where('stored_item_id', $storedItem->id)
            ->where('state', StoredItemAuditDeltaStateEnum::COMPLETED->value)
            ->latest()->first()) {
            $lastAuditDeltaId = $latestAuditDelta->id;
            $lastAuditAt      = $latestAuditDelta->audited_at;

            if ($latestAuditDelta->stored_item_audit_id) {
                $lastAuditId = $latestAuditDelta->stored_item_audit_id;
            } elseif ($latestAuditDeltaWithAudit = DB::table('stored_item_audit_deltas')
                ->where('stored_item_id', $storedItem->id)
                ->whereNotNull('stored_item_audit_id')
                ->where('state', StoredItemAuditDeltaStateEnum::COMPLETED->value)
                ->latest()->first()) {
                $lastAuditId = $latestAuditDeltaWithAudit->stored_item_audit_id;
            }
        }


        $stats = [
            'number_audits' => DB::table('stored_item_audit_deltas')
                ->where('stored_item_id', $storedItem->id)
                ->where('state', StoredItemAuditDeltaStateEnum::COMPLETED->value)->count(),

            'last_audit_at'                   => $lastAuditAt,
            'last_stored_item_audit_delta_id' => $lastAuditDeltaId,
            'last_stored_item_audit_id'       => $lastAuditId,
        ];


        $storedItem->update($stats);
    }
}
