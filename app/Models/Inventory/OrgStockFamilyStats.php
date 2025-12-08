<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 10:56:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Inventory\OrgStockFamilyStats
 *
 * @property int $id
 * @property int $org_stock_family_id
 * @property int $number_org_stocks
 * @property int $number_current_org_stocks active + discontinuing
 * @property int $number_dropped_org_stocks discontinued + abnormality
 * @property int $number_org_stocks_state_active
 * @property int $number_org_stocks_state_discontinuing
 * @property int $number_org_stocks_state_discontinued
 * @property int $number_org_stocks_state_suspended
 * @property int $number_org_stocks_state_abnormality
 * @property int $number_org_stocks_quantity_status_excess
 * @property int $number_org_stocks_quantity_status_ideal
 * @property int $number_org_stocks_quantity_status_low
 * @property int $number_org_stocks_quantity_status_critical
 * @property int $number_org_stocks_quantity_status_out_of_stock
 * @property int $number_org_stocks_quantity_status_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Inventory\OrgStockFamily $orgStockFamily
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockFamilyStats query()
 *
 * @mixin \Eloquent
 */
class OrgStockFamilyStats extends Model
{
    protected $table = 'org_stock_family_stats';

    protected $guarded = [];

    public function orgStockFamily(): BelongsTo
    {
        return $this->belongsTo(OrgStockFamily::class);
    }
}
