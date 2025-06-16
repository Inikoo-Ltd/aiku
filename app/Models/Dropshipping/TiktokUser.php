<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 05 Mar 2025 14:00:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Actions\Dropshipping\Tiktok\Traits\WithTiktokApiServices;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\Traits\HasEmail;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $tiktok_id
 * @property int $customer_id
 * @property bool $status
 * @property string $name
 * @property string|null $username
 * @property string|null $access_token
 * @property string|null $access_token_expire_in
 * @property string|null $refresh_token
 * @property string|null $refresh_token_expire_in
 * @property WebUserAuthTypeEnum $auth_type
 * @property WebUserTypeEnum $state
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $platform_id
 * @property int|null $customer_sales_channel_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dropshipping\PlatformHasClient> $clients
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel|null $customerSalesChannel
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dropshipping\TiktokUserHasOrder> $orders
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dropshipping\TiktokUserHasProduct> $products
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TiktokUser withoutTrashed()
 * @mixin \Eloquent
 */
class TiktokUser extends Model
{
    use HasPermissions;
    use HasEmail;
    use InCustomer;
    use SoftDeletes;
    use WithTiktokApiServices;

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

    public function generateTags(): array
    {
        return ['crm','websites'];
    }

    protected array $auditInclude = [
        'username',
        'name'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('username');
    }

    public function clients(): MorphMany
    {
        return $this->morphMany(PlatformHasClient::class, 'userable');
    }

    public function products(): HasMany
    {
        return $this->hasMany(TiktokUserHasProduct::class, 'tiktok_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TiktokUserHasOrder::class, 'tiktok_user_id');
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }
}
