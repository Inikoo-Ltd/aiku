<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 14:17:10 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Models\Accounting;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $invoice_category_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $invoices
 * @property int $refunds
 * @property string $sales_grp_currency
 * @property string $sales_invoice_category_currency
 * @property string $revenue_grp_currency
 * @property string $revenue_invoice_category_currency
 * @property string $lost_revenue_grp_currency
 * @property string $lost_revenue_invoice_category_currency
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $sales_org_currency
 * @property string $revenue_org_currency
 * @property string $lost_revenue_org_currency
 * @property-read Group $group
 * @property-read \App\Models\Accounting\InvoiceCategory $invoiceCategory
 * @property-read Organisation $organisation
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategorySalesMetrics newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategorySalesMetrics newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceCategorySalesMetrics query()
 *
 * @mixin \Eloquent
 */
class InvoiceCategorySalesMetrics extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function invoiceCategory(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class);
    }
}
