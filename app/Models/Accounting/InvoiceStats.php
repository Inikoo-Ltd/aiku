<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Accounting\InvoiceStats
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $number_invoice_transactions transactions including cancelled
 * @property int $number_positive_invoice_transactions amount>0
 * @property int $number_negative_invoice_transactions amount<0
 * @property int $number_zero_invoice_transactions amount=0
 * @property int $number_current_invoice_transactions transactions excluding cancelled
 * @property int $number_positive_current_invoice_transactions transactions excluding cancelled, amount>0
 * @property int $number_negative_current_invoice_transactions transactions excluding cancelled, amount<0
 * @property int $number_zero_current_invoice_transactions transactions excluding cancelled, amount=0
 * @property int $number_offer_campaigns
 * @property int $number_offers
 * @property int $number_offer_allowances
 * @property int $number_transactions_with_offers
 * @property string $discounts_amount from % offs
 * @property string $giveaways_value_amount Value of goods given for free
 * @property string $cashback_amount
 * @property string|null $org_giveaways_value_amount
 * @property string|null $org_cashback_amount
 * @property string|null $org_discounts_amount
 * @property string|null $grp_discounts_amount
 * @property string|null $grp_giveaways_value_amount
 * @property string|null $grp_cashback_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Accounting\Invoice $invoice
 * @method static Builder<static>|InvoiceStats newModelQuery()
 * @method static Builder<static>|InvoiceStats newQuery()
 * @method static Builder<static>|InvoiceStats query()
 * @mixin Eloquent
 */
class InvoiceStats extends Model
{
    protected $table   = 'invoice_stats';
    protected $guarded = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
