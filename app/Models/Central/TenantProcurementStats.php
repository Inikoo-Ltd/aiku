<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 12:29:33 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;


/**
 * App\Models\Central\TenantProcurementStats
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $number_suppliers
 * @property int $number_active_suppliers
 * @property int $number_agents
 * @property int $number_active_agents
 * @property int $number_active_tenant_agents
 * @property int $number_active_global_agents
 * @property int $number_purchase_orders
 * @property int $number_purchase_orders_state_in_process
 * @property int $number_purchase_orders_state_submitted
 * @property int $number_purchase_orders_state_confirmed
 * @property int $number_purchase_orders_state_dispatched
 * @property int $number_purchase_orders_state_delivered
 * @property int $number_purchase_orders_state_cancelled
 * @property int $number_deliveries
 * @property int $number_workshops
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Central\Tenant $tenant
 * @method static Builder|TenantProcurementStats newModelQuery()
 * @method static Builder|TenantProcurementStats newQuery()
 * @method static Builder|TenantProcurementStats query()
 * @mixin \Eloquent
 */
class TenantProcurementStats extends Model
{
    use UsesLandlordConnection;

    protected $table = 'tenant_procurement_stats';

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
