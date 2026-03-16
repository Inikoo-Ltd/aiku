<?php

namespace App\Models\CRM;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $registration_product_id
 * @property array<array-key, mixed> $top_products
 * @property \Illuminate\Support\Carbon|null $top_products_computed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Product|null $registrationProduct
 * @property-read Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerInterest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerInterest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerInterest query()
 * @mixin \Eloquent
 */
class CustomerInterest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'top_products' => 'array',
            'top_products_computed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function registrationProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'registration_product_id');
    }
}
