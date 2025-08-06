<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 19:13:08 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use App\Enums\Inventory\LocationStock\LocationStockTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * App\Models\Inventory\LocationOrgStock
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $warehouse_id
 * @property int|null $warehouse_area_id
 * @property int $org_stock_id
 * @property int $location_id
 * @property string $quantity in units
 * @property string $value total value based in cost
 * @property string $commercial_value total value based selling price
 * @property LocationStockTypeEnum $type
 * @property int|null $picking_priority
 * @property string|null $notes
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property string|null $audited_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $dropshipping_pipe
 * @property string|null $source_stock_id
 * @property string|null $source_location_id
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Inventory\Location $location
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LocationOrgStock query()
 * @mixin \Eloquent
 */
class LocationOrgStock extends Model implements Auditable
{
    use InWarehouse;
    use HasHistory;

    public $incrementing = true;

    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
        'type'     => LocationStockTypeEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['inventory','location_org_stock'];
    }

    protected array $auditInclude = [
        'type',
        'picking_priority',
        'notes',
        'data',
        'settings',
        'contact_website',
        'identity_document_type',
        'identity_document_number',
    ];


    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class, 'org_stock_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }


}
