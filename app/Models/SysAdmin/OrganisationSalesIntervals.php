<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:22 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * App\Models\SysAdmin\OrganisationOrderingStats
 *
 * @property int $id
 * @property int $organisation_id
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
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static Builder<static>|OrganisationSalesIntervals newModelQuery()
 * @method static Builder<static>|OrganisationSalesIntervals newQuery()
 * @method static Builder<static>|OrganisationSalesIntervals query()
 * @mixin Eloquent
 */
class OrganisationSalesIntervals extends Model
{
    protected $table = 'organisation_sales_intervals';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): HasOneThrough
    {
        return $this->hasOneThrough(
            Group::class,        // Final model
            Organisation::class,         // Intermediate model
            'id',                // Foreign key on Shop
            'id',                // Foreign key on Group
            'organisation_id',           // Local key on this table
            'group_id'           // Local key on Shop
        );
    }


    public function currency(): HasOneThrough
    {
        return $this->hasOneThrough(
            Currency::class,    // Final model we want to reach
            Organisation::class,        // Intermediate model
            'id',               // Foreign key on Shop table
            'id',               // Foreign key on Currency table
            'organisation_id',          // Local key on this table
            'currency_id'       // Local key on Shop table
        );
    }



}
