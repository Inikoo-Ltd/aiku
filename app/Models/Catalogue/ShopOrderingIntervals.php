<?php

/*
 * author Arya Permana - Kirin
 * created on 17-12-2024-13h-29m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Catalogue;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 *
 *
 * @property int $id
 * @property int $shop_id
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
 * @property int $refunds_all
 * @property int $refunds_1y
 * @property int $refunds_1q
 * @property int $refunds_1m
 * @property int $refunds_1w
 * @property int $refunds_3d
 * @property int $refunds_1d
 * @property int $refunds_ytd
 * @property int $refunds_qtd
 * @property int $refunds_mtd
 * @property int $refunds_wtd
 * @property int $refunds_tdy
 * @property int $refunds_lm
 * @property int $refunds_lw
 * @property int $refunds_ld
 * @property int $refunds_1y_ly
 * @property int $refunds_1q_ly
 * @property int $refunds_1m_ly
 * @property int $refunds_1w_ly
 * @property int $refunds_3d_ly
 * @property int $refunds_1d_ly
 * @property int $refunds_ytd_ly
 * @property int $refunds_qtd_ly
 * @property int $refunds_mtd_ly
 * @property int $refunds_wtd_ly
 * @property int $refunds_tdy_ly
 * @property int $refunds_lm_ly
 * @property int $refunds_lw_ly
 * @property int $refunds_ld_ly
 * @property int $refunds_py1
 * @property int $refunds_py2
 * @property int $refunds_py3
 * @property int $refunds_py4
 * @property int $refunds_py5
 * @property int $refunds_pq1
 * @property int $refunds_pq2
 * @property int $refunds_pq3
 * @property int $refunds_pq4
 * @property int $refunds_pq5
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
 * @property int $registrations_all
 * @property int $registrations_1y
 * @property int $registrations_1q
 * @property int $registrations_1m
 * @property int $registrations_1w
 * @property int $registrations_3d
 * @property int $registrations_1d
 * @property int $registrations_ytd
 * @property int $registrations_qtd
 * @property int $registrations_mtd
 * @property int $registrations_wtd
 * @property int $registrations_tdy
 * @property int $registrations_lm
 * @property int $registrations_lw
 * @property int $registrations_ld
 * @property int $registrations_1y_ly
 * @property int $registrations_1q_ly
 * @property int $registrations_1m_ly
 * @property int $registrations_1w_ly
 * @property int $registrations_3d_ly
 * @property int $registrations_1d_ly
 * @property int $registrations_ytd_ly
 * @property int $registrations_qtd_ly
 * @property int $registrations_mtd_ly
 * @property int $registrations_wtd_ly
 * @property int $registrations_tdy_ly
 * @property int $registrations_lm_ly
 * @property int $registrations_lw_ly
 * @property int $registrations_ld_ly
 * @property int $registrations_py1
 * @property int $registrations_py2
 * @property int $registrations_py3
 * @property int $registrations_py4
 * @property int $registrations_py5
 * @property int $registrations_pq1
 * @property int $registrations_pq2
 * @property int $registrations_pq3
 * @property int $registrations_pq4
 * @property int $registrations_pq5
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
 * @property int $baskets_created_all
 * @property int $baskets_created_1y
 * @property int $baskets_created_1q
 * @property int $baskets_created_1m
 * @property int $baskets_created_1w
 * @property int $baskets_created_3d
 * @property int $baskets_created_ytd
 * @property int $baskets_created_qtd
 * @property int $baskets_created_mtd
 * @property int $baskets_created_wtd
 * @property int $baskets_created_tdy
 * @property int $baskets_created_lm
 * @property int $baskets_created_lw
 * @property int $baskets_created_ld
 * @property int $baskets_created_1y_ly
 * @property int $baskets_created_1q_ly
 * @property int $baskets_created_1m_ly
 * @property int $baskets_created_1w_ly
 * @property int $baskets_created_3d_ly
 * @property int $baskets_created_ytd_ly
 * @property int $baskets_created_qtd_ly
 * @property int $baskets_created_mtd_ly
 * @property int $baskets_created_wtd_ly
 * @property int $baskets_created_tdy_ly
 * @property int $baskets_created_lm_ly
 * @property int $baskets_created_lw_ly
 * @property int $baskets_created_ld_ly
 * @property int $baskets_created_py1
 * @property int $baskets_created_py2
 * @property int $baskets_created_py3
 * @property int $baskets_created_py4
 * @property int $baskets_created_py5
 * @property int $baskets_created_pq1
 * @property int $baskets_created_pq2
 * @property int $baskets_created_pq3
 * @property int $baskets_created_pq4
 * @property int $baskets_created_pq5
 * @property int $baskets_updated_all
 * @property int $baskets_updated_1y
 * @property int $baskets_updated_1q
 * @property int $baskets_updated_1m
 * @property int $baskets_updated_1w
 * @property int $baskets_updated_3d
 * @property int $baskets_updated_ytd
 * @property int $baskets_updated_qtd
 * @property int $baskets_updated_mtd
 * @property int $baskets_updated_wtd
 * @property int $baskets_updated_tdy
 * @property int $baskets_updated_lm
 * @property int $baskets_updated_lw
 * @property int $baskets_updated_ld
 * @property int $baskets_updated_1y_ly
 * @property int $baskets_updated_1q_ly
 * @property int $baskets_updated_1m_ly
 * @property int $baskets_updated_1w_ly
 * @property int $baskets_updated_3d_ly
 * @property int $baskets_updated_ytd_ly
 * @property int $baskets_updated_qtd_ly
 * @property int $baskets_updated_mtd_ly
 * @property int $baskets_updated_wtd_ly
 * @property int $baskets_updated_tdy_ly
 * @property int $baskets_updated_lm_ly
 * @property int $baskets_updated_lw_ly
 * @property int $baskets_updated_ld_ly
 * @property int $baskets_updated_py1
 * @property int $baskets_updated_py2
 * @property int $baskets_updated_py3
 * @property int $baskets_updated_py4
 * @property int $baskets_updated_py5
 * @property int $baskets_updated_pq1
 * @property int $baskets_updated_pq2
 * @property int $baskets_updated_pq3
 * @property int $baskets_updated_pq4
 * @property int $baskets_updated_pq5
 * @property-read Group|null $group
 * @property-read Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopOrderingIntervals query()
 * @mixin \Eloquent
 */
class ShopOrderingIntervals extends Model
{
    protected $table = 'shop_ordering_intervals';

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



}
