<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Dec 2024 02:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $master_asset_id
 * @property int $number_product_variants
 * @property int $number_customers_who_favourited
 * @property int $number_customers_who_un_favourited
 * @property int $number_customers_who_reminded
 * @property int $number_customers_who_un_reminded
 * @property int $number_assets
 * @property int $number_current_assets state: active+discontinuing
 * @property int $number_historic_assets
 * @property int $number_assets_state_in_process
 * @property int $number_assets_state_active
 * @property int $number_assets_state_discontinuing
 * @property int $number_assets_state_discontinued
 * @property int $number_assets_type_product
 * @property int $number_assets_type_service
 * @property int $number_assets_type_subscription
 * @property int $number_assets_type_rental
 * @property int $number_assets_type_charge
 * @property int $number_assets_type_shipping_zone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Masters\MasterAsset $masterAsset
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterAssetStats query()
 * @mixin \Eloquent
 */
class MasterAssetStats extends Model
{
    protected $table = 'master_asset_stats';

    protected $guarded = [];

    public function masterAsset(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class);
    }
}
