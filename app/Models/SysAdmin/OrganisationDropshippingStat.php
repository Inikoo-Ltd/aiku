<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 17:28:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $number_customer_clients
 * @property int $number_current_customer_clients
 * @property int $number_portfolios
 * @property int $number_current_portfolios
 * @property int $number_portfolios_platform_shopify
 * @property int $number_portfolios_platform_woocommerce
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationDropshippingStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationDropshippingStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrganisationDropshippingStat query()
 * @mixin \Eloquent
 */
class OrganisationDropshippingStat extends Model
{
    protected $guarded = [];
}
