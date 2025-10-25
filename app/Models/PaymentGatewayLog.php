<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 19:54:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models;

use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property string $gateway
 * @property int|null $payment_id
 * @property PaymentGatewayLogStateEnum $state
 * @property PaymentGatewayLogStatusEnum $status
 * @property array<array-key, mixed>|null $data
 * @property array<array-key, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $type
 * @property string|null $date
 * @property string|null $outcome
 * @property string|null $api_point_model_type
 * @property int|null $api_point_model_id
 * @property string|null $origin
 * @property string|null $operation
 * @property string|null $environment
 * @property-read Group $group
 * @property-read Payment|null $payment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentGatewayLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentGatewayLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentGatewayLog query()
 * @mixin \Eloquent
 */
class PaymentGatewayLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state'   => PaymentGatewayLogStateEnum::class,
        'status'  => PaymentGatewayLogStatusEnum::class,
        'data'    => 'array',
        'payload' => 'array',
    ];

    protected $attributes = [
        'data'    => '{}',
        'payload' => '{}',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
