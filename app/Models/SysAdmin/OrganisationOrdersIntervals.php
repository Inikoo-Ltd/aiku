<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 20:15:32 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $in_baskets_all
 * @property int $in_baskets_1y
 * @property int $in_baskets_1q
 * @property int $in_baskets_1m
 * @property int $in_baskets_1w
 * @property int $in_baskets_3d
 * @property int $in_baskets_1d
 * @property int $in_baskets_ytd
 * @property int $in_baskets_qtd
 * @property int $in_baskets_mtd
 * @property int $in_baskets_wtd
 * @property int $in_baskets_tdy
 * @property int $in_baskets_lm
 * @property int $in_baskets_lw
 * @property int $in_baskets_ld
 * @property int $in_baskets_all_ly
 * @property int $in_baskets_1y_ly
 * @property int $in_baskets_1q_ly
 * @property int $in_baskets_1m_ly
 * @property int $in_baskets_1w_ly
 * @property int $in_baskets_3d_ly
 * @property int $in_baskets_1d_ly
 * @property int $in_baskets_ytd_ly
 * @property int $in_baskets_qtd_ly
 * @property int $in_baskets_mtd_ly
 * @property int $in_baskets_wtd_ly
 * @property int $in_baskets_tdy_ly
 * @property int $in_baskets_lm_ly
 * @property int $in_baskets_lw_ly
 * @property int $in_baskets_ld_ly
 * @property int $in_baskets_py1
 * @property int $in_baskets_py2
 * @property int $in_baskets_py3
 * @property int $in_baskets_py4
 * @property int $in_baskets_py5
 * @property int $in_baskets_pq1
 * @property int $in_baskets_pq2
 * @property int $in_baskets_pq3
 * @property int $in_baskets_pq4
 * @property int $in_baskets_pq5
 * @property int $in_process_all
 * @property int $in_process_1y
 * @property int $in_process_1q
 * @property int $in_process_1m
 * @property int $in_process_1w
 * @property int $in_process_3d
 * @property int $in_process_1d
 * @property int $in_process_ytd
 * @property int $in_process_qtd
 * @property int $in_process_mtd
 * @property int $in_process_wtd
 * @property int $in_process_tdy
 * @property int $in_process_lm
 * @property int $in_process_lw
 * @property int $in_process_ld
 * @property int $in_process_all_ly
 * @property int $in_process_1y_ly
 * @property int $in_process_1q_ly
 * @property int $in_process_1m_ly
 * @property int $in_process_1w_ly
 * @property int $in_process_3d_ly
 * @property int $in_process_1d_ly
 * @property int $in_process_ytd_ly
 * @property int $in_process_qtd_ly
 * @property int $in_process_mtd_ly
 * @property int $in_process_wtd_ly
 * @property int $in_process_tdy_ly
 * @property int $in_process_lm_ly
 * @property int $in_process_lw_ly
 * @property int $in_process_ld_ly
 * @property int $in_process_py1
 * @property int $in_process_py2
 * @property int $in_process_py3
 * @property int $in_process_py4
 * @property int $in_process_py5
 * @property int $in_process_pq1
 * @property int $in_process_pq2
 * @property int $in_process_pq3
 * @property int $in_process_pq4
 * @property int $in_process_pq5
 * @property int $in_process_paid_all
 * @property int $in_process_paid_1y
 * @property int $in_process_paid_1q
 * @property int $in_process_paid_1m
 * @property int $in_process_paid_1w
 * @property int $in_process_paid_3d
 * @property int $in_process_paid_1d
 * @property int $in_process_paid_ytd
 * @property int $in_process_paid_qtd
 * @property int $in_process_paid_mtd
 * @property int $in_process_paid_wtd
 * @property int $in_process_paid_tdy
 * @property int $in_process_paid_lm
 * @property int $in_process_paid_lw
 * @property int $in_process_paid_ld
 * @property int $in_process_paid_all_ly
 * @property int $in_process_paid_1y_ly
 * @property int $in_process_paid_1q_ly
 * @property int $in_process_paid_1m_ly
 * @property int $in_process_paid_1w_ly
 * @property int $in_process_paid_3d_ly
 * @property int $in_process_paid_1d_ly
 * @property int $in_process_paid_ytd_ly
 * @property int $in_process_paid_qtd_ly
 * @property int $in_process_paid_mtd_ly
 * @property int $in_process_paid_wtd_ly
 * @property int $in_process_paid_tdy_ly
 * @property int $in_process_paid_lm_ly
 * @property int $in_process_paid_lw_ly
 * @property int $in_process_paid_ld_ly
 * @property int $in_process_paid_py1
 * @property int $in_process_paid_py2
 * @property int $in_process_paid_py3
 * @property int $in_process_paid_py4
 * @property int $in_process_paid_py5
 * @property int $in_process_paid_pq1
 * @property int $in_process_paid_pq2
 * @property int $in_process_paid_pq3
 * @property int $in_process_paid_pq4
 * @property int $in_process_paid_pq5
 * @property int $in_warehouse_all
 * @property int $in_warehouse_1y
 * @property int $in_warehouse_1q
 * @property int $in_warehouse_1m
 * @property int $in_warehouse_1w
 * @property int $in_warehouse_3d
 * @property int $in_warehouse_1d
 * @property int $in_warehouse_ytd
 * @property int $in_warehouse_qtd
 * @property int $in_warehouse_mtd
 * @property int $in_warehouse_wtd
 * @property int $in_warehouse_tdy
 * @property int $in_warehouse_lm
 * @property int $in_warehouse_lw
 * @property int $in_warehouse_ld
 * @property int $in_warehouse_all_ly
 * @property int $in_warehouse_1y_ly
 * @property int $in_warehouse_1q_ly
 * @property int $in_warehouse_1m_ly
 * @property int $in_warehouse_1w_ly
 * @property int $in_warehouse_3d_ly
 * @property int $in_warehouse_1d_ly
 * @property int $in_warehouse_ytd_ly
 * @property int $in_warehouse_qtd_ly
 * @property int $in_warehouse_mtd_ly
 * @property int $in_warehouse_wtd_ly
 * @property int $in_warehouse_tdy_ly
 * @property int $in_warehouse_lm_ly
 * @property int $in_warehouse_lw_ly
 * @property int $in_warehouse_ld_ly
 * @property int $in_warehouse_py1
 * @property int $in_warehouse_py2
 * @property int $in_warehouse_py3
 * @property int $in_warehouse_py4
 * @property int $in_warehouse_py5
 * @property int $in_warehouse_pq1
 * @property int $in_warehouse_pq2
 * @property int $in_warehouse_pq3
 * @property int $in_warehouse_pq4
 * @property int $in_warehouse_pq5
 * @property int $packed_all
 * @property int $packed_1y
 * @property int $packed_1q
 * @property int $packed_1m
 * @property int $packed_1w
 * @property int $packed_3d
 * @property int $packed_1d
 * @property int $packed_ytd
 * @property int $packed_qtd
 * @property int $packed_mtd
 * @property int $packed_wtd
 * @property int $packed_tdy
 * @property int $packed_lm
 * @property int $packed_lw
 * @property int $packed_ld
 * @property int $packed_all_ly
 * @property int $packed_1y_ly
 * @property int $packed_1q_ly
 * @property int $packed_1m_ly
 * @property int $packed_1w_ly
 * @property int $packed_3d_ly
 * @property int $packed_1d_ly
 * @property int $packed_ytd_ly
 * @property int $packed_qtd_ly
 * @property int $packed_mtd_ly
 * @property int $packed_wtd_ly
 * @property int $packed_tdy_ly
 * @property int $packed_lm_ly
 * @property int $packed_lw_ly
 * @property int $packed_ld_ly
 * @property int $packed_py1
 * @property int $packed_py2
 * @property int $packed_py3
 * @property int $packed_py4
 * @property int $packed_py5
 * @property int $packed_pq1
 * @property int $packed_pq2
 * @property int $packed_pq3
 * @property int $packed_pq4
 * @property int $packed_pq5
 * @property int $in_dispatch_area_all
 * @property int $in_dispatch_area_1y
 * @property int $in_dispatch_area_1q
 * @property int $in_dispatch_area_1m
 * @property int $in_dispatch_area_1w
 * @property int $in_dispatch_area_3d
 * @property int $in_dispatch_area_1d
 * @property int $in_dispatch_area_ytd
 * @property int $in_dispatch_area_qtd
 * @property int $in_dispatch_area_mtd
 * @property int $in_dispatch_area_wtd
 * @property int $in_dispatch_area_tdy
 * @property int $in_dispatch_area_lm
 * @property int $in_dispatch_area_lw
 * @property int $in_dispatch_area_ld
 * @property int $in_dispatch_area_all_ly
 * @property int $in_dispatch_area_1y_ly
 * @property int $in_dispatch_area_1q_ly
 * @property int $in_dispatch_area_1m_ly
 * @property int $in_dispatch_area_1w_ly
 * @property int $in_dispatch_area_3d_ly
 * @property int $in_dispatch_area_1d_ly
 * @property int $in_dispatch_area_ytd_ly
 * @property int $in_dispatch_area_qtd_ly
 * @property int $in_dispatch_area_mtd_ly
 * @property int $in_dispatch_area_wtd_ly
 * @property int $in_dispatch_area_tdy_ly
 * @property int $in_dispatch_area_lm_ly
 * @property int $in_dispatch_area_lw_ly
 * @property int $in_dispatch_area_ld_ly
 * @property int $in_dispatch_area_py1
 * @property int $in_dispatch_area_py2
 * @property int $in_dispatch_area_py3
 * @property int $in_dispatch_area_py4
 * @property int $in_dispatch_area_py5
 * @property int $in_dispatch_area_pq1
 * @property int $in_dispatch_area_pq2
 * @property int $in_dispatch_area_pq3
 * @property int $in_dispatch_area_pq4
 * @property int $in_dispatch_area_pq5
 * @property int $delivery_notes_all
 * @property int $delivery_notes_1y
 * @property int $delivery_notes_1q
 * @property int $delivery_notes_1m
 * @property int $delivery_notes_1w
 * @property int $delivery_notes_3d
 * @property int $delivery_notes_1d
 * @property int $delivery_notes_ytd
 * @property int $delivery_notes_qtd
 * @property int $delivery_notes_mtd
 * @property int $delivery_notes_wtd
 * @property int $delivery_notes_tdy
 * @property int $delivery_notes_lm
 * @property int $delivery_notes_lw
 * @property int $delivery_notes_ld
 * @property int $delivery_notes_all_ly
 * @property int $delivery_notes_1y_ly
 * @property int $delivery_notes_1q_ly
 * @property int $delivery_notes_1m_ly
 * @property int $delivery_notes_1w_ly
 * @property int $delivery_notes_3d_ly
 * @property int $delivery_notes_1d_ly
 * @property int $delivery_notes_ytd_ly
 * @property int $delivery_notes_qtd_ly
 * @property int $delivery_notes_mtd_ly
 * @property int $delivery_notes_wtd_ly
 * @property int $delivery_notes_tdy_ly
 * @property int $delivery_notes_lm_ly
 * @property int $delivery_notes_lw_ly
 * @property int $delivery_notes_ld_ly
 * @property int $delivery_notes_py1
 * @property int $delivery_notes_py2
 * @property int $delivery_notes_py3
 * @property int $delivery_notes_py4
 * @property int $delivery_notes_py5
 * @property int $delivery_notes_pq1
 * @property int $delivery_notes_pq2
 * @property int $delivery_notes_pq3
 * @property int $delivery_notes_pq4
 * @property int $delivery_notes_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrdersIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrdersIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationOrdersIntervals query()
 * @mixin \Eloquent
 */
class OrganisationOrdersIntervals extends Model
{
    protected $table = 'organisation_orders_intervals';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
