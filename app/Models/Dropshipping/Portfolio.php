<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:08:13 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Enums\Catalogue\Portfolio\PortfolioTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $customer_id
 * @property int|null $item_id
 * @property string|null $reference This is the reference that the customer uses to identify the product
 * @property bool $status
 * @property string|null $last_added_at
 * @property string|null $last_removed_at
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property string|null $item_type
 * @property int|null $platform_id
 * @property string|null $item_code no normal field used for improve performance on UI search
 * @property string|null $item_name no normal field used for improve performance on UI search
 * @property int|null $customer_sales_channel_id
 * @property string|null $customer_product_name
 * @property string|null $customer_price
 * @property string|null $customer_description
 * @property string|null $platform_product_id
 * @property string|null $errors_response
 * @property string|null $platform_handle
 * @property PortfolioTypeEnum $type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer $customer
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel|null $customerSalesChannel
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Model|\Eloquent|null $item
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Dropshipping\Platform|null $platform
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Dropshipping\ShopifyUserHasProduct|null $shopifyPortfolio
 * @property-read \App\Models\Dropshipping\PortfolioStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Portfolio newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Portfolio newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Portfolio query()
 * @mixin \Eloquent
 */
class Portfolio extends Model implements Auditable
{
    use InCustomer;
    use HasHistory;
    use HasUniversalSearch;

    protected $casts = [
        'type'       => PortfolioTypeEnum::class,
        'data'       => 'array',
        'settings'   => 'array',
        'status'     => 'boolean',
        'added_at'   => 'datetime',
        'removed_at' => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['crm'];
    }

    protected array $auditInclude = [
        'reference',
        'type',
        'status',
        'last_added_at',
        'removed_at',
    ];

    public function item(): BelongsTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PortfolioStats::class);
    }

    public function shopifyPortfolio(): HasOne
    {
        return $this->hasOne(ShopifyUserHasProduct::class, 'portfolio_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }

}
