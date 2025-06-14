<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Jun 2025 19:58:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDailySnapshot extends Model
{
    use InWarehouse;

    protected $casts = [
        'data' => 'array',
        'date' => 'date',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

}
