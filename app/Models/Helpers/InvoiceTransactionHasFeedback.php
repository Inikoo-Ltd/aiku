<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 19:52:45 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $invoice_transaction_id original transaction
 * @property int|null $post_invoice_transaction_id Associated refund transaction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read InvoiceTransaction|null $invoiceTransaction
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read InvoiceTransaction|null $postInvoiceTransaction
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasFeedback newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasFeedback newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceTransactionHasFeedback query()
 * @mixin \Eloquent
 */
class InvoiceTransactionHasFeedback extends Model
{
    use InShop;

    protected $casts = [
        'fetched_at'         => 'datetime',
        'last_fetched_at'    => 'datetime',
    ];

    protected $guarded = [];


    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class);
    }

    public function postInvoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(InvoiceTransaction::class);
    }




}
