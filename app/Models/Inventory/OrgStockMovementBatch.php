<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Dispatching\BatchCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of one batch a stock movement put on, or took off, a location.
 * A location's stock of a batch is the sum of its rows; whatever the location holds
 * beyond the sum of all its batches has no batch recorded.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $org_stock_movement_id
 * @property int $org_stock_id
 * @property int $location_id
 * @property int $batch_code_id
 * @property string $quantity
 * @property-read BatchCode $batchCode
 * @property-read OrgStockMovement $orgStockMovement
 */
class OrgStockMovementBatch extends Model
{
    protected $guarded = [];

    public function batchCode(): BelongsTo
    {
        return $this->belongsTo(BatchCode::class);
    }

    public function orgStockMovement(): BelongsTo
    {
        return $this->belongsTo(OrgStockMovement::class);
    }
}
