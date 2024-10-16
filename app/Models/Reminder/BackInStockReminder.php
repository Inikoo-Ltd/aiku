<?php
/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-16h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Reminder;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
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
 * @property \Illuminate\Support\Carbon|null $un_reminded_at
 * @property int|null $current_reminder_id
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read Customer $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read Product $product
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder|BackInStockReminder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BackInStockReminder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BackInStockReminder query()
 * @mixin \Eloquent
 */
class BackInStockReminder extends Model
{
    use InShop;

    protected $casts = [
        'un_reminded_at'  => 'datetime'
    ];

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
