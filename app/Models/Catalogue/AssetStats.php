<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 12 Dec 2022 19:41:18 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Catalogue\AssetStats
 *
 * @property int $id
 * @property int $asset_id
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
 * @property-read \App\Models\Catalogue\Asset $asset
 *
 * @method static Builder<static>|AssetStats newModelQuery()
 * @method static Builder<static>|AssetStats newQuery()
 * @method static Builder<static>|AssetStats query()
 *
 * @mixin Eloquent
 */
class AssetStats extends Model
{
    protected $table = 'asset_stats';

    protected $guarded = [];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
