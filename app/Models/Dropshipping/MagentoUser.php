<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 26 Jun 2025 16:57:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Actions\Dropshipping\Magento\WithMagentoApiRequest;
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
 * @property WebUserTypeEnum $state
 * @property WebUserAuthTypeEnum $auth_type
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel|null $customerSalesChannel
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagentoUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagentoUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MagentoUser query()
 * @mixin \Eloquent
 */
class MagentoUser extends Model
{
    use InCustomer;
    use HasSlug;
    use WithMagentoApiRequest;

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
