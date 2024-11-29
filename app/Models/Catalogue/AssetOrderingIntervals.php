<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 10:44:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $asset_id
 * @property int $invoices_all
 * @property int $invoices_1y
 * @property int $invoices_1q
 * @property int $invoices_1m
 * @property int $invoices_1w
 * @property int $invoices_3d
 * @property int $invoices_1d
 * @property int $invoices_ytd
 * @property int $invoices_qtd
 * @property int $invoices_mtd
 * @property int $invoices_wtd
 * @property int $invoices_tdy
 * @property int $invoices_lm
 * @property int $invoices_lw
 * @property int $invoices_ld
 * @property int $invoices_all_ly
 * @property int $invoices_1y_ly
 * @property int $invoices_1q_ly
 * @property int $invoices_1m_ly
 * @property int $invoices_1w_ly
 * @property int $invoices_3d_ly
 * @property int $invoices_1d_ly
 * @property int $invoices_ytd_ly
 * @property int $invoices_qtd_ly
 * @property int $invoices_mtd_ly
 * @property int $invoices_wtd_ly
 * @property int $invoices_tdy_ly
 * @property int $invoices_lm_ly
 * @property int $invoices_lw_ly
 * @property int $invoices_ld_ly
 * @property int $invoices_py1
 * @property int $invoices_py2
 * @property int $invoices_py3
 * @property int $invoices_py4
 * @property int $invoices_py5
 * @property int $invoices_pq1
 * @property int $invoices_pq2
 * @property int $invoices_pq3
 * @property int $invoices_pq4
 * @property int $invoices_pq5
 * @property int $orders_all
 * @property int $orders_1y
 * @property int $orders_1q
 * @property int $orders_1m
 * @property int $orders_1w
 * @property int $orders_3d
 * @property int $orders_1d
 * @property int $orders_ytd
 * @property int $orders_qtd
 * @property int $orders_mtd
 * @property int $orders_wtd
 * @property int $orders_tdy
 * @property int $orders_lm
 * @property int $orders_lw
 * @property int $orders_ld
 * @property int $orders_all_ly
 * @property int $orders_1y_ly
 * @property int $orders_1q_ly
 * @property int $orders_1m_ly
 * @property int $orders_1w_ly
 * @property int $orders_3d_ly
 * @property int $orders_1d_ly
 * @property int $orders_ytd_ly
 * @property int $orders_qtd_ly
 * @property int $orders_mtd_ly
 * @property int $orders_wtd_ly
 * @property int $orders_tdy_ly
 * @property int $orders_lm_ly
 * @property int $orders_lw_ly
 * @property int $orders_ld_ly
 * @property int $orders_py1
 * @property int $orders_py2
 * @property int $orders_py3
 * @property int $orders_py4
 * @property int $orders_py5
 * @property int $orders_pq1
 * @property int $orders_pq2
 * @property int $orders_pq3
 * @property int $orders_pq4
 * @property int $orders_pq5
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
 * @property int $customers_invoiced_all
 * @property int $customers_invoiced_1y
 * @property int $customers_invoiced_1q
 * @property int $customers_invoiced_1m
 * @property int $customers_invoiced_1w
 * @property int $customers_invoiced_3d
 * @property int $customers_invoiced_1d
 * @property int $customers_invoiced_ytd
 * @property int $customers_invoiced_qtd
 * @property int $customers_invoiced_mtd
 * @property int $customers_invoiced_wtd
 * @property int $customers_invoiced_tdy
 * @property int $customers_invoiced_lm
 * @property int $customers_invoiced_lw
 * @property int $customers_invoiced_ld
 * @property int $customers_invoiced_all_ly
 * @property int $customers_invoiced_1y_ly
 * @property int $customers_invoiced_1q_ly
 * @property int $customers_invoiced_1m_ly
 * @property int $customers_invoiced_1w_ly
 * @property int $customers_invoiced_3d_ly
 * @property int $customers_invoiced_1d_ly
 * @property int $customers_invoiced_ytd_ly
 * @property int $customers_invoiced_qtd_ly
 * @property int $customers_invoiced_mtd_ly
 * @property int $customers_invoiced_wtd_ly
 * @property int $customers_invoiced_tdy_ly
 * @property int $customers_invoiced_lm_ly
 * @property int $customers_invoiced_lw_ly
 * @property int $customers_invoiced_ld_ly
 * @property int $customers_invoiced_py1
 * @property int $customers_invoiced_py2
 * @property int $customers_invoiced_py3
 * @property int $customers_invoiced_py4
 * @property int $customers_invoiced_py5
 * @property int $customers_invoiced_pq1
 * @property int $customers_invoiced_pq2
 * @property int $customers_invoiced_pq3
 * @property int $customers_invoiced_pq4
 * @property int $customers_invoiced_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssetOrderingIntervals query()
 * @mixin \Eloquent
 */
class AssetOrderingIntervals extends Model
{
    protected $guarded = [];

}
