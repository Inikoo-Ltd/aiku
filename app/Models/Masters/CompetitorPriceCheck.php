<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Masters;

use App\Models\Helpers\Currency;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * A price seen by a person on a marketplace that cannot be read by itself (INI-217), kept as history:
 * each monthly check adds a row, so the price over time can be reported. The unit prices are in
 * currency_id (the competitor's when we have a price in it, else the group's) as they were that day.
 *
 * @property int $id
 * @property int $group_id
 * @property int $competitor_id
 * @property int $competitor_product_id
 * @property int $master_asset_id
 * @property string $price in the competitor's currency, for the whole pack
 * @property string $units units in their pack
 * @property int|null $delivery_days
 * @property int $currency_id of our_unit_price and their_unit_price
 * @property string|null $our_unit_price our RRP for shoppers' marketplaces, our price otherwise
 * @property string|null $their_unit_price
 * @property int|null $difference_pct negative means they are cheaper
 * @property int|null $image_id the screenshot of their listing showing the price, taken by the person who checked
 * @property int|null $checked_by_user_id
 * @property \Illuminate\Support\Carbon $checked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Competitor $competitor
 * @property-read CompetitorProduct $competitorProduct
 * @property-read MasterAsset $masterAsset
 * @property-read User|null $checkedBy
 * @property-read Currency $currency
 */
class CompetitorPriceCheck extends Model implements HasMedia
{
    use HasImage;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }

    public function competitorProduct(): BelongsTo
    {
        return $this->belongsTo(CompetitorProduct::class);
    }

    public function masterAsset(): BelongsTo
    {
        return $this->belongsTo(MasterAsset::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }
}
