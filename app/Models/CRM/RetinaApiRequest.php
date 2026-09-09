<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $customer_sales_channel_id
 * @property int|null $web_user_id
 * @property int|null $personal_access_token_id
 * @property string|null $route_name
 * @property string $method
 * @property string $path
 * @property array<array-key, mixed>|null $route_parameters
 * @property array<array-key, mixed>|null $payload
 * @property int $status
 * @property string|null $message
 * @property int|null $response_id
 * @property int|null $duration_ms
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaApiRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaApiRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaApiRequest query()
 * @mixin \Eloquent
 */
class RetinaApiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'payload'          => 'array',
            'created_at'       => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }
}
