<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Dropshipping;

use App\Actions\Dropshipping\Amazon\Traits\WithAmazonApiRequest;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property string $slug
 * @property bool $status
 * @property string $name
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int|null $platform_id
 * @property int|null $customer_sales_channel_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property WebUserTypeEnum $state
 * @property WebUserAuthTypeEnum $auth_type
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel|null $customerSalesChannel
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AmazonUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AmazonUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AmazonUser query()
 * @mixin \Eloquent
 */
class AmazonUser extends Model
{
    use InCustomer;
    use HasSlug;
    use WithAmazonApiRequest;

    protected $guarded = [];

    protected $casts = [
        'data'      => 'array',
        'settings'  => 'array',
        'state'     => WebUserTypeEnum::class,
        'auth_type' => WebUserAuthTypeEnum::class,
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->name;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }
}
