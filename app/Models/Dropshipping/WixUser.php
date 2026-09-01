<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Actions\Dropshipping\Wix\Traits\WithWixApiServices;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $customer_id
 * @property string $slug
 * @property bool $status
 * @property string $name
 * @property string $wix_instance_id
 * @property string|null $wix_site_id
 * @property string|null $email
 * @property string|null $site_url
 * @property string|null $access_token
 * @property string|null $access_token_expire_in
 * @property string|null $refresh_token
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int|null $customer_sales_channel_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property WebUserTypeEnum $state
 * @property WebUserAuthTypeEnum $auth_type
 * @property-read \App\Models\CRM\Customer|null $customer
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel|null $customerSalesChannel
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WixUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WixUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WixUser query()
 * @mixin \Eloquent
 */
class WixUser extends Model implements Auditable
{
    use InCustomer;
    use HasSlug;
    use SoftDeletes;
    use WithWixApiServices;
    use HasHistory;

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

    protected array $auditInclude = [
        'name',
        'status',
        'state'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }

    public function debugWebhooks(): MorphMany
    {
        return $this->morphMany(DebugWebhooks::class, 'model');
    }

    public function generateTags(): array
    {
        return ['crm', 'websites'];
    }
}
