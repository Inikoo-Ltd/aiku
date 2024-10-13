<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\Catalogue\Asset;
use App\Models\Helpers\Currency;
use App\Models\Ordering\Transaction;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 * @property-read Asset|null $asset
 * @property-read Currency|null $currency
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read Model|\Eloquent $item
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read Transaction|null $transaction
 * @method static Builder|InvoiceTransaction newModelQuery()
 * @method static Builder|InvoiceTransaction newQuery()
 * @method static Builder|InvoiceTransaction onlyTrashed()
 * @method static Builder|InvoiceTransaction query()
 * @method static Builder|InvoiceTransaction withTrashed()
 * @method static Builder|InvoiceTransaction withoutTrashed()
 * @mixin Eloquent
 */
class InvoiceTransaction extends Model
{
    use SoftDeletes;
    use InCustomer;

    protected $table = 'invoice_transactions';

    protected $casts = [
        'data'           => 'array',
        'date'           => 'datetime',
        'quantity'       => 'decimal:3',
        'gross_amount'   => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'grp_exchange'   => 'decimal:4',
        'org_exchange'   => 'decimal:4',
        'grp_net_amount' => 'decimal:2',
        'org_net_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

}
