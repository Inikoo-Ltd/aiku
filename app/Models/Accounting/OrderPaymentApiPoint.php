<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Models\Ordering\Order;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $order_id
 * @property string $ulid
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Order $order
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Accounting\PaymentAccountShop|null $paymentAccountShop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPaymentApiPoint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPaymentApiPoint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderPaymentApiPoint query()
 * @mixin \Eloquent
 */
class OrderPaymentApiPoint extends Model
{
    use InOrganisation;

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentAccountShop(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountShop::class);
    }

}
