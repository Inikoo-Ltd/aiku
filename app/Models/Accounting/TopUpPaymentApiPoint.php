<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:22:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Models\CRM\Customer;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property int|null $top_up_id
 * @property string $ulid
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property TopUpPaymentApiPointStateEnum $state
 * @property-read Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Accounting\PaymentAccountShop|null $paymentAccountShop
 * @property-read \App\Models\Accounting\TopUp|null $topUp
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUpPaymentApiPoint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUpPaymentApiPoint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TopUpPaymentApiPoint query()
 * @mixin \Eloquent
 */
class TopUpPaymentApiPoint extends Model
{
    use InOrganisation;

    protected $casts = [
        'data'         => 'array',
        'processed_at' => 'datetime',
        'state'        => TopUpPaymentApiPointStateEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function topUp(): BelongsTo
    {
        return $this->belongsTo(TopUp::class);
    }

    public function paymentAccountShop(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountShop::class);
    }
}
