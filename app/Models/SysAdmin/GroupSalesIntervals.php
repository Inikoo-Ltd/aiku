<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:32:22 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupSalesIntervals
 *
 * @property int $id
 * @property int $group_id
 * @property string $group_amount_all
 * @property string $group_amount_1y
 * @property string $group_amount_1q
 * @property string $group_amount_1m
 * @property string $group_amount_1w
 * @property string $group_amount_ytd
 * @property string $group_amount_qtd
 * @property string $group_amount_mtd
 * @property string $group_amount_wtd
 * @property string $group_amount_lm
 * @property string $group_amount_lw
 * @property string $group_amount_yda
 * @property string $group_amount_tdy
 * @property string $group_amount_all_ly
 * @property string $group_amount_1y_ly
 * @property string $group_amount_1q_ly
 * @property string $group_amount_1m_ly
 * @property string $group_amount_1w_ly
 * @property string $group_amount_ytd_ly
 * @property string $group_amount_qtd_ly
 * @property string $group_amount_mtd_ly
 * @property string $group_amount_wtd_ly
 * @property string $group_amount_lm_ly
 * @property string $group_amount_lw_ly
 * @property string $group_amount_yda_ly
 * @property string $group_amount_tdy_ly
 * @property string $group_amount_py1
 * @property string $group_amount_py2
 * @property string $group_amount_py3
 * @property string $group_amount_py4
 * @property string $group_amount_py5
 * @property string $group_amount_pq1
 * @property string $group_amount_pq2
 * @property string $group_amount_pq3
 * @property string $group_amount_pq4
 * @property string $group_amount_pq5
 * @property string $invoices_all
 * @property string $invoices_1y
 * @property string $invoices_1q
 * @property string $invoices_1m
 * @property string $invoices_1w
 * @property string $invoices_ytd
 * @property string $invoices_qtd
 * @property string $invoices_mtd
 * @property string $invoices_wtd
 * @property string $invoices_lm
 * @property string $invoices_lw
 * @property string $invoices_yda
 * @property string $invoices_tdy
 * @property string $invoices_all_ly
 * @property string $invoices_1y_ly
 * @property string $invoices_1q_ly
 * @property string $invoices_1m_ly
 * @property string $invoices_1w_ly
 * @property string $invoices_ytd_ly
 * @property string $invoices_qtd_ly
 * @property string $invoices_mtd_ly
 * @property string $invoices_wtd_ly
 * @property string $invoices_lm_ly
 * @property string $invoices_lw_ly
 * @property string $invoices_yda_ly
 * @property string $invoices_tdy_ly
 * @property string $invoices_py1
 * @property string $invoices_py2
 * @property string $invoices_py3
 * @property string $invoices_py4
 * @property string $invoices_py5
 * @property string $invoices_pq1
 * @property string $invoices_pq2
 * @property string $invoices_pq3
 * @property string $invoices_pq4
 * @property string $invoices_pq5
 * @property string $refunds_all
 * @property string $refunds_1y
 * @property string $refunds_1q
 * @property string $refunds_1m
 * @property string $refunds_1w
 * @property string $refunds_ytd
 * @property string $refunds_qtd
 * @property string $refunds_mtd
 * @property string $refunds_wtd
 * @property string $refunds_lm
 * @property string $refunds_lw
 * @property string $refunds_yda
 * @property string $refunds_tdy
 * @property string $refunds_all_ly
 * @property string $refunds_1y_ly
 * @property string $refunds_1q_ly
 * @property string $refunds_1m_ly
 * @property string $refunds_1w_ly
 * @property string $refunds_ytd_ly
 * @property string $refunds_qtd_ly
 * @property string $refunds_mtd_ly
 * @property string $refunds_wtd_ly
 * @property string $refunds_lm_ly
 * @property string $refunds_lw_ly
 * @property string $refunds_yda_ly
 * @property string $refunds_tdy_ly
 * @property string $refunds_py1
 * @property string $refunds_py2
 * @property string $refunds_py3
 * @property string $refunds_py4
 * @property string $refunds_py5
 * @property string $refunds_pq1
 * @property string $refunds_pq2
 * @property string $refunds_pq3
 * @property string $refunds_pq4
 * @property string $refunds_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder|GroupSalesIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupSalesIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupSalesIntervals query()
 * @mixin \Eloquent
 */
class GroupSalesIntervals extends Model
{
    protected $table = 'group_sales_intervals';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
