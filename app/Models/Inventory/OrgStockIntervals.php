<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Dec 2024 22:52:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $org_stock_id
 * @property string $revenue_all
 * @property string $revenue_1y
 * @property string $revenue_1q
 * @property string $revenue_1m
 * @property string $revenue_1w
 * @property string $revenue_3d
 * @property string $revenue_1d
 * @property string $revenue_ytd
 * @property string $revenue_qtd
 * @property string $revenue_mtd
 * @property string $revenue_wtd
 * @property string $revenue_tdy
 * @property string $revenue_lm
 * @property string $revenue_lw
 * @property string $revenue_ld
 * @property string $revenue_1y_ly
 * @property string $revenue_1q_ly
 * @property string $revenue_1m_ly
 * @property string $revenue_1w_ly
 * @property string $revenue_3d_ly
 * @property string $revenue_1d_ly
 * @property string $revenue_ytd_ly
 * @property string $revenue_qtd_ly
 * @property string $revenue_mtd_ly
 * @property string $revenue_wtd_ly
 * @property string $revenue_tdy_ly
 * @property string $revenue_lm_ly
 * @property string $revenue_lw_ly
 * @property string $revenue_ld_ly
 * @property string $revenue_py1
 * @property string $revenue_py2
 * @property string $revenue_py3
 * @property string $revenue_py4
 * @property string $revenue_py5
 * @property string $revenue_pq1
 * @property string $revenue_pq2
 * @property string $revenue_pq3
 * @property string $revenue_pq4
 * @property string $revenue_pq5
 * @property string $revenue_org_currency_all
 * @property string $revenue_org_currency_1y
 * @property string $revenue_org_currency_1q
 * @property string $revenue_org_currency_1m
 * @property string $revenue_org_currency_1w
 * @property string $revenue_org_currency_3d
 * @property string $revenue_org_currency_1d
 * @property string $revenue_org_currency_ytd
 * @property string $revenue_org_currency_qtd
 * @property string $revenue_org_currency_mtd
 * @property string $revenue_org_currency_wtd
 * @property string $revenue_org_currency_tdy
 * @property string $revenue_org_currency_lm
 * @property string $revenue_org_currency_lw
 * @property string $revenue_org_currency_ld
 * @property string $revenue_org_currency_1y_ly
 * @property string $revenue_org_currency_1q_ly
 * @property string $revenue_org_currency_1m_ly
 * @property string $revenue_org_currency_1w_ly
 * @property string $revenue_org_currency_3d_ly
 * @property string $revenue_org_currency_1d_ly
 * @property string $revenue_org_currency_ytd_ly
 * @property string $revenue_org_currency_qtd_ly
 * @property string $revenue_org_currency_mtd_ly
 * @property string $revenue_org_currency_wtd_ly
 * @property string $revenue_org_currency_tdy_ly
 * @property string $revenue_org_currency_lm_ly
 * @property string $revenue_org_currency_lw_ly
 * @property string $revenue_org_currency_ld_ly
 * @property string $revenue_org_currency_py1
 * @property string $revenue_org_currency_py2
 * @property string $revenue_org_currency_py3
 * @property string $revenue_org_currency_py4
 * @property string $revenue_org_currency_py5
 * @property string $revenue_org_currency_pq1
 * @property string $revenue_org_currency_pq2
 * @property string $revenue_org_currency_pq3
 * @property string $revenue_org_currency_pq4
 * @property string $revenue_org_currency_pq5
 * @property string $revenue_grp_currency_all
 * @property string $revenue_grp_currency_1y
 * @property string $revenue_grp_currency_1q
 * @property string $revenue_grp_currency_1m
 * @property string $revenue_grp_currency_1w
 * @property string $revenue_grp_currency_3d
 * @property string $revenue_grp_currency_1d
 * @property string $revenue_grp_currency_ytd
 * @property string $revenue_grp_currency_qtd
 * @property string $revenue_grp_currency_mtd
 * @property string $revenue_grp_currency_wtd
 * @property string $revenue_grp_currency_tdy
 * @property string $revenue_grp_currency_lm
 * @property string $revenue_grp_currency_lw
 * @property string $revenue_grp_currency_ld
 * @property string $revenue_grp_currency_1y_ly
 * @property string $revenue_grp_currency_1q_ly
 * @property string $revenue_grp_currency_1m_ly
 * @property string $revenue_grp_currency_1w_ly
 * @property string $revenue_grp_currency_3d_ly
 * @property string $revenue_grp_currency_1d_ly
 * @property string $revenue_grp_currency_ytd_ly
 * @property string $revenue_grp_currency_qtd_ly
 * @property string $revenue_grp_currency_mtd_ly
 * @property string $revenue_grp_currency_wtd_ly
 * @property string $revenue_grp_currency_tdy_ly
 * @property string $revenue_grp_currency_lm_ly
 * @property string $revenue_grp_currency_lw_ly
 * @property string $revenue_grp_currency_ld_ly
 * @property string $revenue_grp_currency_py1
 * @property string $revenue_grp_currency_py2
 * @property string $revenue_grp_currency_py3
 * @property string $revenue_grp_currency_py4
 * @property string $revenue_grp_currency_py5
 * @property string $revenue_grp_currency_pq1
 * @property string $revenue_grp_currency_pq2
 * @property string $revenue_grp_currency_pq3
 * @property string $revenue_grp_currency_pq4
 * @property string $revenue_grp_currency_pq5
 * @property string $profit_all
 * @property string $profit_1y
 * @property string $profit_1q
 * @property string $profit_1m
 * @property string $profit_1w
 * @property string $profit_3d
 * @property string $profit_1d
 * @property string $profit_ytd
 * @property string $profit_qtd
 * @property string $profit_mtd
 * @property string $profit_wtd
 * @property string $profit_tdy
 * @property string $profit_lm
 * @property string $profit_lw
 * @property string $profit_ld
 * @property string $profit_1y_ly
 * @property string $profit_1q_ly
 * @property string $profit_1m_ly
 * @property string $profit_1w_ly
 * @property string $profit_3d_ly
 * @property string $profit_1d_ly
 * @property string $profit_ytd_ly
 * @property string $profit_qtd_ly
 * @property string $profit_mtd_ly
 * @property string $profit_wtd_ly
 * @property string $profit_tdy_ly
 * @property string $profit_lm_ly
 * @property string $profit_lw_ly
 * @property string $profit_ld_ly
 * @property string $profit_py1
 * @property string $profit_py2
 * @property string $profit_py3
 * @property string $profit_py4
 * @property string $profit_py5
 * @property string $profit_pq1
 * @property string $profit_pq2
 * @property string $profit_pq3
 * @property string $profit_pq4
 * @property string $profit_pq5
 * @property string $profit_org_currency_all
 * @property string $profit_org_currency_1y
 * @property string $profit_org_currency_1q
 * @property string $profit_org_currency_1m
 * @property string $profit_org_currency_1w
 * @property string $profit_org_currency_3d
 * @property string $profit_org_currency_1d
 * @property string $profit_org_currency_ytd
 * @property string $profit_org_currency_qtd
 * @property string $profit_org_currency_mtd
 * @property string $profit_org_currency_wtd
 * @property string $profit_org_currency_tdy
 * @property string $profit_org_currency_lm
 * @property string $profit_org_currency_lw
 * @property string $profit_org_currency_ld
 * @property string $profit_org_currency_1y_ly
 * @property string $profit_org_currency_1q_ly
 * @property string $profit_org_currency_1m_ly
 * @property string $profit_org_currency_1w_ly
 * @property string $profit_org_currency_3d_ly
 * @property string $profit_org_currency_1d_ly
 * @property string $profit_org_currency_ytd_ly
 * @property string $profit_org_currency_qtd_ly
 * @property string $profit_org_currency_mtd_ly
 * @property string $profit_org_currency_wtd_ly
 * @property string $profit_org_currency_tdy_ly
 * @property string $profit_org_currency_lm_ly
 * @property string $profit_org_currency_lw_ly
 * @property string $profit_org_currency_ld_ly
 * @property string $profit_org_currency_py1
 * @property string $profit_org_currency_py2
 * @property string $profit_org_currency_py3
 * @property string $profit_org_currency_py4
 * @property string $profit_org_currency_py5
 * @property string $profit_org_currency_pq1
 * @property string $profit_org_currency_pq2
 * @property string $profit_org_currency_pq3
 * @property string $profit_org_currency_pq4
 * @property string $profit_org_currency_pq5
 * @property string $profit_grp_currency_all
 * @property string $profit_grp_currency_1y
 * @property string $profit_grp_currency_1q
 * @property string $profit_grp_currency_1m
 * @property string $profit_grp_currency_1w
 * @property string $profit_grp_currency_3d
 * @property string $profit_grp_currency_1d
 * @property string $profit_grp_currency_ytd
 * @property string $profit_grp_currency_qtd
 * @property string $profit_grp_currency_mtd
 * @property string $profit_grp_currency_wtd
 * @property string $profit_grp_currency_tdy
 * @property string $profit_grp_currency_lm
 * @property string $profit_grp_currency_lw
 * @property string $profit_grp_currency_ld
 * @property string $profit_grp_currency_1y_ly
 * @property string $profit_grp_currency_1q_ly
 * @property string $profit_grp_currency_1m_ly
 * @property string $profit_grp_currency_1w_ly
 * @property string $profit_grp_currency_3d_ly
 * @property string $profit_grp_currency_1d_ly
 * @property string $profit_grp_currency_ytd_ly
 * @property string $profit_grp_currency_qtd_ly
 * @property string $profit_grp_currency_mtd_ly
 * @property string $profit_grp_currency_wtd_ly
 * @property string $profit_grp_currency_tdy_ly
 * @property string $profit_grp_currency_lm_ly
 * @property string $profit_grp_currency_lw_ly
 * @property string $profit_grp_currency_ld_ly
 * @property string $profit_grp_currency_py1
 * @property string $profit_grp_currency_py2
 * @property string $profit_grp_currency_py3
 * @property string $profit_grp_currency_py4
 * @property string $profit_grp_currency_py5
 * @property string $profit_grp_currency_pq1
 * @property string $profit_grp_currency_pq2
 * @property string $profit_grp_currency_pq3
 * @property string $profit_grp_currency_pq4
 * @property string $profit_grp_currency_pq5
 * @property int $dispatched_all
 * @property int $dispatched_1y
 * @property int $dispatched_1q
 * @property int $dispatched_1m
 * @property int $dispatched_1w
 * @property int $dispatched_3d
 * @property int $dispatched_1d
 * @property int $dispatched_ytd
 * @property int $dispatched_qtd
 * @property int $dispatched_mtd
 * @property int $dispatched_wtd
 * @property int $dispatched_tdy
 * @property int $dispatched_lm
 * @property int $dispatched_lw
 * @property int $dispatched_ld
 * @property int $dispatched_1y_ly
 * @property int $dispatched_1q_ly
 * @property int $dispatched_1m_ly
 * @property int $dispatched_1w_ly
 * @property int $dispatched_3d_ly
 * @property int $dispatched_1d_ly
 * @property int $dispatched_ytd_ly
 * @property int $dispatched_qtd_ly
 * @property int $dispatched_mtd_ly
 * @property int $dispatched_wtd_ly
 * @property int $dispatched_tdy_ly
 * @property int $dispatched_lm_ly
 * @property int $dispatched_lw_ly
 * @property int $dispatched_ld_ly
 * @property int $dispatched_py1
 * @property int $dispatched_py2
 * @property int $dispatched_py3
 * @property int $dispatched_py4
 * @property int $dispatched_py5
 * @property int $dispatched_pq1
 * @property int $dispatched_pq2
 * @property int $dispatched_pq3
 * @property int $dispatched_pq4
 * @property int $dispatched_pq5
 * @property int $org_stock_movements_all
 * @property int $org_stock_movements_1y
 * @property int $org_stock_movements_1q
 * @property int $org_stock_movements_1m
 * @property int $org_stock_movements_1w
 * @property int $org_stock_movements_3d
 * @property int $org_stock_movements_1d
 * @property int $org_stock_movements_ytd
 * @property int $org_stock_movements_qtd
 * @property int $org_stock_movements_mtd
 * @property int $org_stock_movements_wtd
 * @property int $org_stock_movements_tdy
 * @property int $org_stock_movements_lm
 * @property int $org_stock_movements_lw
 * @property int $org_stock_movements_ld
 * @property int $org_stock_movements_1y_ly
 * @property int $org_stock_movements_1q_ly
 * @property int $org_stock_movements_1m_ly
 * @property int $org_stock_movements_1w_ly
 * @property int $org_stock_movements_3d_ly
 * @property int $org_stock_movements_1d_ly
 * @property int $org_stock_movements_ytd_ly
 * @property int $org_stock_movements_qtd_ly
 * @property int $org_stock_movements_mtd_ly
 * @property int $org_stock_movements_wtd_ly
 * @property int $org_stock_movements_tdy_ly
 * @property int $org_stock_movements_lm_ly
 * @property int $org_stock_movements_lw_ly
 * @property int $org_stock_movements_ld_ly
 * @property int $org_stock_movements_py1
 * @property int $org_stock_movements_py2
 * @property int $org_stock_movements_py3
 * @property int $org_stock_movements_py4
 * @property int $org_stock_movements_py5
 * @property int $org_stock_movements_pq1
 * @property int $org_stock_movements_pq2
 * @property int $org_stock_movements_pq3
 * @property int $org_stock_movements_pq4
 * @property int $org_stock_movements_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventory\OrgStock $orgStock
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrgStockIntervals query()
 * @mixin \Eloquent
 */
class OrgStockIntervals extends Model
{
    protected $table = 'org_stock_intervals';

    protected $guarded = [];


    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }
}
