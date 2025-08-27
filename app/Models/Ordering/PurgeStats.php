<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Nov 2024 17:36:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purge_id
 * @property int $estimated_number_orders
 * @property int $estimated_number_transactions
 * @property int $number_purged_orders
 * @property int $number_purged_orders_status_in_process
 * @property int $number_purged_orders_status_purged
 * @property int $number_purged_orders_status_exculpated
 * @property int $number_purged_orders_status_cancelled
 * @property int $number_purged_orders_status_error
 * @property int $number_purged_transactions
 * @property int $currency_id
 * @property string $estimated_net_amount
 * @property string $estimated_org_net_amount
 * @property string $estimated_grp_net_amount
 * @property string $purged_net_amount
 * @property string $purged_org_net_amount
 * @property string $purged_grp_net_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ordering\Purge $purge
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgeStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgeStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PurgeStats query()
 * @mixin \Eloquent
 */
class PurgeStats extends Model
{
    protected $guarded = [];

    public function purge(): BelongsTo
    {
        return $this->belongsTo(Purge::class);
    }
}
