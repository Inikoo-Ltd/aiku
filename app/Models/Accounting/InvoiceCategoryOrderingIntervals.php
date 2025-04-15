<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 10:41:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 *
 *
 * @property int $id
 * @property int $invoice_category_id
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
 * @property-read Group|null $group
 * @property-read \App\Models\Accounting\InvoiceCategory $invoiceCategory
 * @property-read Organisation|null $organisation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategoryOrderingIntervals query()
 * @mixin \Eloquent
 */
class InvoiceCategoryOrderingIntervals extends Model
{
    protected $table = 'invoice_category_ordering_intervals';
    protected $guarded = [];

    public function invoiceCategory(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class);
    }

    public function organisation(): HasOneThrough
    {
        return $this->hasOneThrough(
            Organisation::class, // Final model
            InvoiceCategory::class,         // Intermediate model
            'id',                // Foreign key on Shop
            'id',                // Foreign key on Organisation
            'invoice_category_id',           // Local key on this table
            'organisation_id'    // Local key on Shop
        );
    }

    public function group(): HasOneThrough
    {
        return $this->hasOneThrough(
            Group::class,        // Final model
            InvoiceCategory::class,         // Intermediate model
            'id',                // Foreign key on Shop
            'id',                // Foreign key on Group
            'invoice_category_id',           // Local key on this table
            'group_id'           // Local key on Shop
        );
    }




}
