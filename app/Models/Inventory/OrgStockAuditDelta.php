<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 11:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\OrgStockAuditDelta\OrgStockAuditDeltaTypeEnum;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int $org_stock_id
 * @property int $location_id
 * @property \Illuminate\Support\Carbon|null $audited_at
 * @property int|null $user_id User who audited the stock
 * @property string|null $original_quantity
 * @property string $audited_quantity
 * @property OrgStockAuditDeltaTypeEnum $type Addition, Subtraction, NoChange
 * @property string|null $reason
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Inventory\OrgStockAudit|null $orgStockAudit
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAuditDelta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAuditDelta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockAuditDelta query()
 * @mixin \Eloquent
 */
class OrgStockAuditDelta extends Model
{
    use InWarehouse;

    protected $guarded = [];

    protected $casts = [
        'type'       => OrgStockAuditDeltaTypeEnum::class,
        'audited_at' => 'datetime',
        'data'       => 'array'
    ];

    protected $attributes = [
        'data' => '{}'
    ];

    public function orgStockAudit(): BelongsTo
    {
        return $this->belongsTo(OrgStockAudit::class);
    }

}
