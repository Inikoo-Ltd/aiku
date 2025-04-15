<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Jan 2024 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
 * @property string $sales_tdy
 * @property string $sales_lm
 * @property string $sales_lw
 * @property string $sales_ld
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
 * @property string $sales_tdy_ly
 * @property string $sales_lm_ly
 * @property string $sales_lw_ly
 * @property string $sales_ld_ly
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
 * @property string $sales_org_currency_tdy
 * @property string $sales_org_currency_lm
 * @property string $sales_org_currency_lw
 * @property string $sales_org_currency_ld
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
 * @property string $sales_org_currency_tdy_ly
 * @property string $sales_org_currency_lm_ly
 * @property string $sales_org_currency_lw_ly
 * @property string $sales_org_currency_ld_ly
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
 * @property string $sales_grp_currency_tdy
 * @property string $sales_grp_currency_lm
 * @property string $sales_grp_currency_lw
 * @property string $sales_grp_currency_ld
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
 * @property string $sales_grp_currency_tdy_ly
 * @property string $sales_grp_currency_lm_ly
 * @property string $sales_grp_currency_lw_ly
 * @property string $sales_grp_currency_ld_ly
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
 * @property string $baskets_created_all
 * @property string $baskets_created_1y
 * @property string $baskets_created_1q
 * @property string $baskets_created_1m
 * @property string $baskets_created_1w
 * @property string $baskets_created_3d
 * @property string $baskets_created_ytd
 * @property string $baskets_created_qtd
 * @property string $baskets_created_mtd
 * @property string $baskets_created_wtd
 * @property string $baskets_created_tdy
 * @property string $baskets_created_lm
 * @property string $baskets_created_lw
 * @property string $baskets_created_ld
 * @property string $baskets_created_1y_ly
 * @property string $baskets_created_1q_ly
 * @property string $baskets_created_1m_ly
 * @property string $baskets_created_1w_ly
 * @property string $baskets_created_3d_ly
 * @property string $baskets_created_ytd_ly
 * @property string $baskets_created_qtd_ly
 * @property string $baskets_created_mtd_ly
 * @property string $baskets_created_wtd_ly
 * @property string $baskets_created_tdy_ly
 * @property string $baskets_created_lm_ly
 * @property string $baskets_created_lw_ly
 * @property string $baskets_created_ld_ly
 * @property string $baskets_created_py1
 * @property string $baskets_created_py2
 * @property string $baskets_created_py3
 * @property string $baskets_created_py4
 * @property string $baskets_created_py5
 * @property string $baskets_created_pq1
 * @property string $baskets_created_pq2
 * @property string $baskets_created_pq3
 * @property string $baskets_created_pq4
 * @property string $baskets_created_pq5
 * @property string $baskets_updated_all
 * @property string $baskets_updated_1y
 * @property string $baskets_updated_1q
 * @property string $baskets_updated_1m
 * @property string $baskets_updated_1w
 * @property string $baskets_updated_3d
 * @property string $baskets_updated_ytd
 * @property string $baskets_updated_qtd
 * @property string $baskets_updated_mtd
 * @property string $baskets_updated_wtd
 * @property string $baskets_updated_tdy
 * @property string $baskets_updated_lm
 * @property string $baskets_updated_lw
 * @property string $baskets_updated_ld
 * @property string $baskets_updated_1y_ly
 * @property string $baskets_updated_1q_ly
 * @property string $baskets_updated_1m_ly
 * @property string $baskets_updated_1w_ly
 * @property string $baskets_updated_3d_ly
 * @property string $baskets_updated_ytd_ly
 * @property string $baskets_updated_qtd_ly
 * @property string $baskets_updated_mtd_ly
 * @property string $baskets_updated_wtd_ly
 * @property string $baskets_updated_tdy_ly
 * @property string $baskets_updated_lm_ly
 * @property string $baskets_updated_lw_ly
 * @property string $baskets_updated_ld_ly
 * @property string $baskets_updated_py1
 * @property string $baskets_updated_py2
 * @property string $baskets_updated_py3
 * @property string $baskets_updated_py4
 * @property string $baskets_updated_py5
 * @property string $baskets_updated_pq1
 * @property string $baskets_updated_pq2
 * @property string $baskets_updated_pq3
 * @property string $baskets_updated_pq4
 * @property string $baskets_updated_pq5
 * @property string $baskets_created_org_currency_all
 * @property string $baskets_created_org_currency_1y
 * @property string $baskets_created_org_currency_1q
 * @property string $baskets_created_org_currency_1m
 * @property string $baskets_created_org_currency_1w
 * @property string $baskets_created_org_currency_3d
 * @property string $baskets_created_org_currency_ytd
 * @property string $baskets_created_org_currency_qtd
 * @property string $baskets_created_org_currency_mtd
 * @property string $baskets_created_org_currency_wtd
 * @property string $baskets_created_org_currency_tdy
 * @property string $baskets_created_org_currency_lm
 * @property string $baskets_created_org_currency_lw
 * @property string $baskets_created_org_currency_ld
 * @property string $baskets_created_org_currency_1y_ly
 * @property string $baskets_created_org_currency_1q_ly
 * @property string $baskets_created_org_currency_1m_ly
 * @property string $baskets_created_org_currency_1w_ly
 * @property string $baskets_created_org_currency_3d_ly
 * @property string $baskets_created_org_currency_ytd_ly
 * @property string $baskets_created_org_currency_qtd_ly
 * @property string $baskets_created_org_currency_mtd_ly
 * @property string $baskets_created_org_currency_wtd_ly
 * @property string $baskets_created_org_currency_tdy_ly
 * @property string $baskets_created_org_currency_lm_ly
 * @property string $baskets_created_org_currency_lw_ly
 * @property string $baskets_created_org_currency_ld_ly
 * @property string $baskets_created_org_currency_py1
 * @property string $baskets_created_org_currency_py2
 * @property string $baskets_created_org_currency_py3
 * @property string $baskets_created_org_currency_py4
 * @property string $baskets_created_org_currency_py5
 * @property string $baskets_created_org_currency_pq1
 * @property string $baskets_created_org_currency_pq2
 * @property string $baskets_created_org_currency_pq3
 * @property string $baskets_created_org_currency_pq4
 * @property string $baskets_created_org_currency_pq5
 * @property string $baskets_updated_org_currency_all
 * @property string $baskets_updated_org_currency_1y
 * @property string $baskets_updated_org_currency_1q
 * @property string $baskets_updated_org_currency_1m
 * @property string $baskets_updated_org_currency_1w
 * @property string $baskets_updated_org_currency_3d
 * @property string $baskets_updated_org_currency_ytd
 * @property string $baskets_updated_org_currency_qtd
 * @property string $baskets_updated_org_currency_mtd
 * @property string $baskets_updated_org_currency_wtd
 * @property string $baskets_updated_org_currency_tdy
 * @property string $baskets_updated_org_currency_lm
 * @property string $baskets_updated_org_currency_lw
 * @property string $baskets_updated_org_currency_ld
 * @property string $baskets_updated_org_currency_1y_ly
 * @property string $baskets_updated_org_currency_1q_ly
 * @property string $baskets_updated_org_currency_1m_ly
 * @property string $baskets_updated_org_currency_1w_ly
 * @property string $baskets_updated_org_currency_3d_ly
 * @property string $baskets_updated_org_currency_ytd_ly
 * @property string $baskets_updated_org_currency_qtd_ly
 * @property string $baskets_updated_org_currency_mtd_ly
 * @property string $baskets_updated_org_currency_wtd_ly
 * @property string $baskets_updated_org_currency_tdy_ly
 * @property string $baskets_updated_org_currency_lm_ly
 * @property string $baskets_updated_org_currency_lw_ly
 * @property string $baskets_updated_org_currency_ld_ly
 * @property string $baskets_updated_org_currency_py1
 * @property string $baskets_updated_org_currency_py2
 * @property string $baskets_updated_org_currency_py3
 * @property string $baskets_updated_org_currency_py4
 * @property string $baskets_updated_org_currency_py5
 * @property string $baskets_updated_org_currency_pq1
 * @property string $baskets_updated_org_currency_pq2
 * @property string $baskets_updated_org_currency_pq3
 * @property string $baskets_updated_org_currency_pq4
 * @property string $baskets_updated_org_currency_pq5
 * @property string $baskets_created_grp_currency_all
 * @property string $baskets_created_grp_currency_1y
 * @property string $baskets_created_grp_currency_1q
 * @property string $baskets_created_grp_currency_1m
 * @property string $baskets_created_grp_currency_1w
 * @property string $baskets_created_grp_currency_3d
 * @property string $baskets_created_grp_currency_ytd
 * @property string $baskets_created_grp_currency_qtd
 * @property string $baskets_created_grp_currency_mtd
 * @property string $baskets_created_grp_currency_wtd
 * @property string $baskets_created_grp_currency_tdy
 * @property string $baskets_created_grp_currency_lm
 * @property string $baskets_created_grp_currency_lw
 * @property string $baskets_created_grp_currency_ld
 * @property string $baskets_created_grp_currency_1y_ly
 * @property string $baskets_created_grp_currency_1q_ly
 * @property string $baskets_created_grp_currency_1m_ly
 * @property string $baskets_created_grp_currency_1w_ly
 * @property string $baskets_created_grp_currency_3d_ly
 * @property string $baskets_created_grp_currency_ytd_ly
 * @property string $baskets_created_grp_currency_qtd_ly
 * @property string $baskets_created_grp_currency_mtd_ly
 * @property string $baskets_created_grp_currency_wtd_ly
 * @property string $baskets_created_grp_currency_tdy_ly
 * @property string $baskets_created_grp_currency_lm_ly
 * @property string $baskets_created_grp_currency_lw_ly
 * @property string $baskets_created_grp_currency_ld_ly
 * @property string $baskets_created_grp_currency_py1
 * @property string $baskets_created_grp_currency_py2
 * @property string $baskets_created_grp_currency_py3
 * @property string $baskets_created_grp_currency_py4
 * @property string $baskets_created_grp_currency_py5
 * @property string $baskets_created_grp_currency_pq1
 * @property string $baskets_created_grp_currency_pq2
 * @property string $baskets_created_grp_currency_pq3
 * @property string $baskets_created_grp_currency_pq4
 * @property string $baskets_created_grp_currency_pq5
 * @property string $baskets_updated_grp_currency_all
 * @property string $baskets_updated_grp_currency_1y
 * @property string $baskets_updated_grp_currency_1q
 * @property string $baskets_updated_grp_currency_1m
 * @property string $baskets_updated_grp_currency_1w
 * @property string $baskets_updated_grp_currency_3d
 * @property string $baskets_updated_grp_currency_ytd
 * @property string $baskets_updated_grp_currency_qtd
 * @property string $baskets_updated_grp_currency_mtd
 * @property string $baskets_updated_grp_currency_wtd
 * @property string $baskets_updated_grp_currency_tdy
 * @property string $baskets_updated_grp_currency_lm
 * @property string $baskets_updated_grp_currency_lw
 * @property string $baskets_updated_grp_currency_ld
 * @property string $baskets_updated_grp_currency_1y_ly
 * @property string $baskets_updated_grp_currency_1q_ly
 * @property string $baskets_updated_grp_currency_1m_ly
 * @property string $baskets_updated_grp_currency_1w_ly
 * @property string $baskets_updated_grp_currency_3d_ly
 * @property string $baskets_updated_grp_currency_ytd_ly
 * @property string $baskets_updated_grp_currency_qtd_ly
 * @property string $baskets_updated_grp_currency_mtd_ly
 * @property string $baskets_updated_grp_currency_wtd_ly
 * @property string $baskets_updated_grp_currency_tdy_ly
 * @property string $baskets_updated_grp_currency_lm_ly
 * @property string $baskets_updated_grp_currency_lw_ly
 * @property string $baskets_updated_grp_currency_ld_ly
 * @property string $baskets_updated_grp_currency_py1
 * @property string $baskets_updated_grp_currency_py2
 * @property string $baskets_updated_grp_currency_py3
 * @property string $baskets_updated_grp_currency_py4
 * @property string $baskets_updated_grp_currency_py5
 * @property string $baskets_updated_grp_currency_pq1
 * @property string $baskets_updated_grp_currency_pq2
 * @property string $baskets_updated_grp_currency_pq3
 * @property string $baskets_updated_grp_currency_pq4
 * @property string $baskets_updated_grp_currency_pq5
 * @property-read Currency|null $currency
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
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

    public function organisation(): HasOneThrough
    {
        return $this->hasOneThrough(
            Organisation::class, // Final model
            Shop::class,         // Intermediate model
            'id',                // Foreign key on Shop
            'id',                // Foreign key on Organisation
            'shop_id',           // Local key on this table
            'organisation_id'    // Local key on Shop
        );
    }

    public function group(): HasOneThrough
    {
        return $this->hasOneThrough(
            Group::class,        // Final model
            Shop::class,         // Intermediate model
            'id',                // Foreign key on Shop
            'id',                // Foreign key on Group
            'shop_id',           // Local key on this table
            'group_id'           // Local key on Shop
        );
    }

    public function currency(): HasOneThrough
    {
        return $this->hasOneThrough(
            Currency::class,    // Final model we want to reach
            Shop::class,        // Intermediate model
            'id',               // Foreign key on Shop table
            'id',               // Foreign key on Currency table
            'shop_id',          // Local key on this table
            'currency_id'       // Local key on Shop table
        );
    }

}
