<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 19:06:52 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\InWarehouse;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int|null $warehouse_area_id
 * @property \Illuminate\Support\Carbon $date
 * @property OrgStockMovementClassEnum $class
 * @property OrgStockMovementTypeEnum $type
 * @property OrgStockMovementFlowEnum $flow
 * @property bool $is_delivered
 * @property bool $is_received
 * @property int|null $stock_family_id
 * @property int|null $org_stock_family_id
 * @property int|null $stock_id
 * @property int $org_stock_id
 * @property int|null $location_id
 * @property string|null $operation_type
 * @property int|null $operation_id
 * @property numeric|null $quantity
 * @property numeric $org_amount
 * @property numeric $grp_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property string|null $fixed
 * @property numeric|null $audited_quantity
 * @property string|null $running_quantity running quantity on org_stock/location
 * @property string|null $running_quantity_org_stock running quantity on org_stock
 * @property bool|null $fixed_internal_helper
 * @property numeric|null $cost_per_sku
 * @property int|null user_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Inventory\Location|null $location
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static Builder<static>|OrgStockMovement newModelQuery()
 * @method static Builder<static>|OrgStockMovement newQuery()
 * @method static Builder<static>|OrgStockMovement query()
 * @mixin Eloquent
 */
class OrgStockMovement extends Model
{
    use InWarehouse;

    protected $dateFormat = 'Y-m-d H:i:s.uP';

    protected $casts = [
        'data'       => 'array',
        'type'       => OrgStockMovementTypeEnum::class,
        'flow'       => OrgStockMovementFlowEnum::class,
        'class'      => OrgStockMovementClassEnum::class,
        'date'       => 'datetime',
        'quantity'         => 'decimal:3',
        'audited_quantity' => 'decimal:6',
        'amount'           => 'decimal:3',
        'grp_amount' => 'decimal:3',
        'org_amount' => 'decimal:3',
        'cost_per_sku' => 'decimal:6',
        'fixed_internal_helper' => 'boolean',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
