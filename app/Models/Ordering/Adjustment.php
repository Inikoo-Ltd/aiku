<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 16:14:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\Adjustment\AdjustmentTypeEnum;
use App\Models\Helpers\Currency;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $currency_id
 * @property AdjustmentTypeEnum $type
 * @property numeric $net_amount
 * @property string|null $org_net_amount
 * @property string|null $grp_net_amount
 * @property numeric|null $tax_amount
 * @property string|null $org_tax_amount
 * @property string|null $grp_tax_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Adjustment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Adjustment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Adjustment query()
 * @mixin \Eloquent
 */
class Adjustment extends Model
{
    use InShop;

    protected $guarded = [];

    protected $casts = [
        'type'            => AdjustmentTypeEnum::class,
        'net_amount'      => 'decimal:2',
        'net_org_amount'  => 'decimal:2',
        'net_grp_amount'  => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'tax_org_amount'  => 'decimal:2',
        'tax_grp_amount'  => 'decimal:2',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime'
    ];


    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }


}
