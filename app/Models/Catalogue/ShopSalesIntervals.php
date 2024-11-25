<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Jan 2024 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Catalogue\ShopOrderingStats
 *
 * @property int $id
 * @property int $shop_id
 * @property string $sales_all
 * @property string $sales_1y
 * @property string $sales_1q
 * @property string $sales_1m
 * @property string $sales_1w
 * @property string $sales_3d
 * @property string $sales_1d
 * @property string $sales_ytd
 * @property string $sales_qtd
 * @property string $sales_mtd
 * @property string $sales_wtd
 * @property string $sales_lm
 * @property string $sales_lw
 * @property string $sales_all_ly
 * @property string $sales_1y_ly
 * @property string $sales_1q_ly
 * @property string $sales_1m_ly
 * @property string $sales_1w_ly
 * @property string $sales_3d_ly
 * @property string $sales_1d_ly
 * @property string $sales_ytd_ly
 * @property string $sales_qtd_ly
 * @property string $sales_mtd_ly
 * @property string $sales_wtd_ly
 * @property string $sales_lm_ly
 * @property string $sales_lw_ly
 * @property string $sales_py1
 * @property string $sales_py2
 * @property string $sales_py3
 * @property string $sales_py4
 * @property string $sales_py5
 * @property string $sales_pq1
 * @property string $sales_pq2
 * @property string $sales_pq3
 * @property string $sales_pq4
 * @property string $sales_pq5
 * @property string $sales_org_currency_all
 * @property string $sales_org_currency_1y
 * @property string $sales_org_currency_1q
 * @property string $sales_org_currency_1m
 * @property string $sales_org_currency_1w
 * @property string $sales_org_currency_3d
 * @property string $sales_org_currency_1d
 * @property string $sales_org_currency_ytd
 * @property string $sales_org_currency_qtd
 * @property string $sales_org_currency_mtd
 * @property string $sales_org_currency_wtd
 * @property string $sales_org_currency_lm
 * @property string $sales_org_currency_lw
 * @property string $sales_org_currency_all_ly
 * @property string $sales_org_currency_1y_ly
 * @property string $sales_org_currency_1q_ly
 * @property string $sales_org_currency_1m_ly
 * @property string $sales_org_currency_1w_ly
 * @property string $sales_org_currency_3d_ly
 * @property string $sales_org_currency_1d_ly
 * @property string $sales_org_currency_ytd_ly
 * @property string $sales_org_currency_qtd_ly
 * @property string $sales_org_currency_mtd_ly
 * @property string $sales_org_currency_wtd_ly
 * @property string $sales_org_currency_lm_ly
 * @property string $sales_org_currency_lw_ly
 * @property string $sales_org_currency_py1
 * @property string $sales_org_currency_py2
 * @property string $sales_org_currency_py3
 * @property string $sales_org_currency_py4
 * @property string $sales_org_currency_py5
 * @property string $sales_org_currency_pq1
 * @property string $sales_org_currency_pq2
 * @property string $sales_org_currency_pq3
 * @property string $sales_org_currency_pq4
 * @property string $sales_org_currency_pq5
 * @property string $sales_grp_currency_all
 * @property string $sales_grp_currency_1y
 * @property string $sales_grp_currency_1q
 * @property string $sales_grp_currency_1m
 * @property string $sales_grp_currency_1w
 * @property string $sales_grp_currency_3d
 * @property string $sales_grp_currency_1d
 * @property string $sales_grp_currency_ytd
 * @property string $sales_grp_currency_qtd
 * @property string $sales_grp_currency_mtd
 * @property string $sales_grp_currency_wtd
 * @property string $sales_grp_currency_lm
 * @property string $sales_grp_currency_lw
 * @property string $sales_grp_currency_all_ly
 * @property string $sales_grp_currency_1y_ly
 * @property string $sales_grp_currency_1q_ly
 * @property string $sales_grp_currency_1m_ly
 * @property string $sales_grp_currency_1w_ly
 * @property string $sales_grp_currency_3d_ly
 * @property string $sales_grp_currency_1d_ly
 * @property string $sales_grp_currency_ytd_ly
 * @property string $sales_grp_currency_qtd_ly
 * @property string $sales_grp_currency_mtd_ly
 * @property string $sales_grp_currency_wtd_ly
 * @property string $sales_grp_currency_lm_ly
 * @property string $sales_grp_currency_lw_ly
 * @property string $sales_grp_currency_py1
 * @property string $sales_grp_currency_py2
 * @property string $sales_grp_currency_py3
 * @property string $sales_grp_currency_py4
 * @property string $sales_grp_currency_py5
 * @property string $sales_grp_currency_pq1
 * @property string $sales_grp_currency_pq2
 * @property string $sales_grp_currency_pq3
 * @property string $sales_grp_currency_pq4
 * @property string $sales_grp_currency_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopSalesIntervals query()
 * @mixin \Eloquent
 */
class ShopSalesIntervals extends Model
{
    protected $table = 'shop_sales_intervals';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
