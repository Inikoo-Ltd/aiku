<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:43:05 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $collection_id
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionSalesIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionSalesIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CollectionSalesIntervals query()
 * @mixin \Eloquent
 */
class CollectionSalesIntervals extends Model
{
    protected $guarded = [];
}
