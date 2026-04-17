<?php

namespace App\Models;

use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property int $customer_sales_channel_id
 * @property string $bundleable_type
 * @property int $bundleable_id
 * @property bool $status
 * @property bool $has_valid_platform_product_id
 * @property bool $exist_in_platform
 * @property bool $platform_status
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $bundleable
 * @property-read Customer|null $customer
 * @property-read CustomerSalesChannel|null $customerSalesChannel
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BundleItem> $items
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bundle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bundle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bundle query()
 * @mixin \Eloquent
 */
class Bundle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'settings' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
        'settings' => '{}'
    ];

    public function bundleable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }
}
