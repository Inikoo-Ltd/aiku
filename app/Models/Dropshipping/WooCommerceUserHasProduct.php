<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Aug 2024 17:27:59 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 *
 *
 * @property int $id
 * @property int $woo_commerce_user_id
 * @property int $product_id
 * @property int|null $portfolio_id
 * @property int $woo_commerce_product_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dropshipping\WooCommerceUser $wooCommerceUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WooCommerceUserHasProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WooCommerceUserHasProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WooCommerceUserHasProduct query()
 * @mixin \Eloquent
 */
class WooCommerceUserHasProduct extends Pivot
{
    protected $table = 'wc_user_has_products';

    public function wooCommerceUser(): BelongsTo
    {
        return $this->belongsTo(WooCommerceUser::class);
    }
}
