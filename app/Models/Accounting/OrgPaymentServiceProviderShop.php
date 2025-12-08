<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:30:32 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\Accounting\OrgPaymentServiceProviderShop
 *
 * @property int $id
 * @property int $shop_id
 * @property int $org_payment_service_provider_id
 * @property int $currency_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static Builder<static>|OrgPaymentServiceProviderShop newModelQuery()
 * @method static Builder<static>|OrgPaymentServiceProviderShop newQuery()
 * @method static Builder<static>|OrgPaymentServiceProviderShop query()
 *
 * @mixin Eloquent
 */
class OrgPaymentServiceProviderShop extends Pivot
{
    public $incrementing = true;

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];
}
