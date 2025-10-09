<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 10:52:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Models\Catalogue\Product;
use App\Models\Traits\InShop;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $family_id
 * @property int|null $sub_department_id
 * @property int|null $department_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $unfavourited_at
 * @property int|null $current_favourite_id
 * @property string|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Product $product
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Website|null $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favourite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favourite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favourite query()
 * @mixin \Eloquent
 */
class Favourite extends Model
{
    use InShop;

    protected $casts = [
        'unfavourited_at'  => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $guarded = [];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
