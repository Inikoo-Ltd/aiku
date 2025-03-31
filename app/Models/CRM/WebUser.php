<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:55:39 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Audits\Redactors\PasswordRedactor;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Models\Analytics\WebUserRequest;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasEmail;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasRoles;
use App\Models\Traits\InCustomer;
use App\Models\Traits\IsUserable;
use App\Models\Web\Website;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\CRM\WebUser
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $website_id
 * @property int $customer_id
 * @property string $slug
 * @property bool $is_root
 * @property WebUserTypeEnum $type
 * @property bool $status
 * @property string $username
 * @property string|null $email
 * @property string|null $email_verified_at
 * @property string|null $password
 * @property WebUserAuthTypeEnum $auth_type
 * @property string|null $remember_token
 * @property int $number_api_tokens
 * @property string|null $about
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property bool $reset_password
 * @property int $language_id
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property string|null $contact_name
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Helpers\Language $language
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read Organisation $organisation
 * @property-read Collection<int, \App\Models\CRM\WebUserPasswordReset> $passwordResets
 * @property-read Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read Shop|null $shop
 * @property-read \App\Models\CRM\WebUserStats|null $stats
 * @property-read Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Collection<int, WebUserRequest> $webUserRequests
 * @property-read Website $website
 * @method static \Database\Factories\CRM\WebUserFactory factory($count = null, $state = [])
 * @method static Builder<static>|WebUser newModelQuery()
 * @method static Builder<static>|WebUser newQuery()
 * @method static Builder<static>|WebUser onlyTrashed()
 * @method static Builder<static>|WebUser permission($permissions, $without = false)
 * @method static Builder<static>|WebUser query()
 * @method static Builder<static>|WebUser role($roles, $guard = null, $without = false)
 * @method static Builder<static>|WebUser withTrashed()
 * @method static Builder<static>|WebUser withoutPermission($permissions)
 * @method static Builder<static>|WebUser withoutRole($roles, $guard = null)
 * @method static Builder<static>|WebUser withoutTrashed()
 * @mixin Eloquent
 */
class WebUser extends Authenticatable implements HasMedia, Auditable
{
    use IsUserable;
    use HasPermissions;
    use HasEmail;
    use HasImage;
    use InCustomer;
    use HasRoles;

    protected $casts = [

        'data'            => 'array',
        'settings'        => 'array',
        'status'          => 'boolean',
        'type'            => WebUserTypeEnum::class,
        'auth_type'       => WebUserAuthTypeEnum::class,
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [
    ];

    public function generateTags(): array
    {
        return ['crm', 'websites'];
    }

    protected array $auditInclude = [
        'username',
        'email',
        'password',
    ];

    protected array $attributeModifiers = [
        'password' => PasswordRedactor::class,
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                $slug = $this->username;

                return preg_replace('/@/', '_at_', $slug);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WebUserStats::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function webUserRequests(): HasMany
    {
        return $this->hasMany(WebUserRequest::class);
    }

    public function passwordResets(): HasMany
    {
        return $this->hasMany(WebUserPasswordReset::class);
    }

}
