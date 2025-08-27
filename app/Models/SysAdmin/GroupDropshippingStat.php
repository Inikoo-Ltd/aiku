<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 17:28:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property int $number_customer_clients
 * @property int $number_current_customer_clients
 * @property int $number_portfolios
 * @property int $number_current_portfolios
 * @property int $number_portfolios_platform_shopify
 * @property int $number_portfolios_platform_woocommerce
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDropshippingStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDropshippingStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupDropshippingStat query()
 * @mixin \Eloquent
 */
class GroupDropshippingStat extends Model
{
    protected $guarded = [];
}
