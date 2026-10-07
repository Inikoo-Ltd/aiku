<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Accounting\InvoiceCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The morning's AI tip on how a shop, or one of its invoice categories, can reach the month's
 * target, written from the figures kept in facts.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $invoice_category_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $tip
 * @property array<string, mixed> $facts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read InvoiceCategory|null $invoiceCategory
 * @mixin \Eloquent
 */
class SalesTargetTip extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date'  => 'date',
        'facts' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function invoiceCategory(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class);
    }
}
