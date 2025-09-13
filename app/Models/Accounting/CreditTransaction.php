<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Aug 2024 10:38:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $top_up_id
 * @property int|null $payment_id
 * @property CreditTransactionTypeEnum $type
 * @property \Illuminate\Support\Carbon $date
 * @property numeric $amount
 * @property numeric|null $running_amount
 * @property int $currency_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property numeric|null $grp_amount
 * @property numeric|null $org_amount
 * @property string|null $notes
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property CreditTransactionReasonEnum|null $reason
 * @property-read Currency $currency
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Accounting\Payment|null $payment
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Accounting\TopUp|null $topUp
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditTransaction query()
 * @mixin \Eloquent
 */
class CreditTransaction extends Model
{
    use InCustomer;

    protected $casts = [
        'data'            => 'array',
        'date'            => 'datetime',
        'amount'          => 'decimal:2',
        'running_amount'  => 'decimal:2',
        'grp_exchange'    => 'decimal:4',
        'org_exchange'    => 'decimal:4',
        'grp_amount'      => 'decimal:2',
        'org_amount'      => 'decimal:2',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'type'            => CreditTransactionTypeEnum::class,
        'reason'          => CreditTransactionReasonEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function topUp(): BelongsTo
    {
        return $this->belongsTo(TopUp::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }


}
