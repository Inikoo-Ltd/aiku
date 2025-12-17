<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Created: Wed, 17 Dec 2025 09:55:33 WITA
 * Location: Lembeng Beach, Bali, Indonesia
 */

namespace App\Models\Masters;

use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $master_shop_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $refunds
 * @property int $orders
 * @property int $registrations
 * @property string $baskets_created_grp_currency
 * @property string $sales_grp_currency
 * @property string $revenue_grp_currency
 * @property string $lost_revenue_grp_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Masters\MasterShop $masterShop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopSalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopSalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterShopSalesMetrics query()
 * @mixin \Eloquent
 */
class MasterShopSalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }
}
