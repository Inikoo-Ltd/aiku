<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Production;

use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestUnitEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantities in units of the raw material, except skos_to_bring and delivered_skos, which
 * are in SKOs as the warehouse counts them.
 *
 * @property int $id
 * @property int $production_material_request_id
 * @property int|null $raw_material_id
 * @property int $org_stock_id
 * @property numeric|null $needed_units
 * @property numeric|null $missing_units
 * @property numeric|null $box_units
 * @property int|null $boxes
 * @property numeric|null $skos_to_bring
 * @property array<int, string>|null $job_orders
 * @property int|null $production_drop_point_id
 * @property numeric|null $requested_quantity
 * @property ProductionMaterialRequestUnitEnum|null $requested_unit
 * @property bool $is_urgent
 * @property string|null $needed_by
 * @property string|null $note
 * @property numeric $unbooked_skos
 * @property string|null $short_reason
 * @property \Illuminate\Support\Carbon|null $short_at
 * @property ProductionMaterialRequestItemStateEnum $state
 * @property numeric $delivered_skos
 * @property int|null $delivered_by_user_id
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $deliveredBy
 * @property-read OrgStock $orgStock
 * @property-read RawMaterial|null $rawMaterial
 * @property-read ProductionDropPoint|null $dropPoint
 * @property-read ProductionMaterialRequest $request
 * @mixin \Eloquent
 */
class ProductionMaterialRequestItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'state'          => ProductionMaterialRequestItemStateEnum::class,
            'requested_unit' => ProductionMaterialRequestUnitEnum::class,
            'job_orders'     => 'array',
            'is_urgent'      => 'boolean',
            'delivered_at'   => 'datetime',
            'short_at'       => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialRequest::class, 'production_material_request_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function dropPoint(): BelongsTo
    {
        return $this->belongsTo(ProductionDropPoint::class, 'production_drop_point_id')->withTrashed();
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }
}
