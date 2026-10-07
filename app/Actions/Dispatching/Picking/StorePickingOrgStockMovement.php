<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sept 2026 16:55:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Dispatching\Picking;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes a pick off its location, queued after the pick is saved. The pick row is locked and
 * read again, so the movement always carries the pick's quantity at that moment: a pick edited
 * or deleted before this ran is taken off as edited, or not at all (HELP-3418). UpdatePicking
 * and DeletePicking lock the same row, and a pick that already has its movement is skipped,
 * which makes a retry harmless.
 */
class StorePickingOrgStockMovement
{
    use AsAction;

    public int $jobTries = 5;

    public int $jobBackoff = 5;

    public string $jobQueue = 'stock-control';

    public function handle(int $pickingId, ?int $userId): void
    {
        DB::transaction(function () use ($pickingId, $userId) {
            $picking = Picking::lockForUpdate()->find($pickingId);

            if (!$picking || $picking->org_stock_movement_id || (float)$picking->quantity == 0.0) {
                return;
            }

            StoreOrgStockMovement::run(
                $picking->orgStock,
                $picking->location,
                [
                    'quantity' => -$picking->quantity,
                    'type'     => OrgStockMovementTypeEnum::PICKED,
                    'user_id'  => $userId,
                ],
                $picking
            );
        });
    }
}
