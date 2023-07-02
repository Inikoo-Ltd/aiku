<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 30 Apr 2023 14:23:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Auth;

use App\Enums\Auth\User\UserAuthTypeEnum;
use App\Models\Central\CentralMedia;
use App\Models\Media\GroupMedia;
use App\Models\Tenancy\Tenant;
use App\Models\Traits\UsesGroupConnection;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Multitenancy\TenantCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Auth\GroupUser
 *
 * @property int $id
 * @property string $username
 * @property string|null $password
 * @property UserAuthTypeEnum $auth_type
 * @property string|null $contact_name no-normalised depends on parent
 * @property string|null $email mirror group_users.email
 * @property string|null $about
 * @property bool $status
 * @property int|null $avatar_id
 * @property array|null $data
 * @property int $number_users
 * @property int $number_active_users
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $legacy_password source password
 * @property-read CentralMedia|null $avatar
 * @property-read MediaCollection<int, GroupMedia> $media
 * @property-read TenantCollection<int, Tenant> $tenants
 * @method static Builder|GroupUser newModelQuery()
 * @method static Builder|GroupUser newQuery()
 * @method static Builder|GroupUser onlyTrashed()
 * @method static Builder|GroupUser query()
 * @method static Builder|GroupUser withTrashed()
 * @method static Builder|GroupUser withoutTrashed()
 * @mixin Eloquent
 */
class GroupUser extends Model implements HasMedia
{
    use HasSlug;
    use UsesGroupConnection;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $casts = [
        'data'      => 'array',
        'status'    => 'boolean',
        'auth_type' => UserAuthTypeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('username')
            ->saveSlugsTo('username');
    }

    public function avatar(): HasOne
    {
        return $this->hasOne(CentralMedia::class, 'id', 'avatar_id');
    }

    public function tenants(): BelongsToMany
    {
        return $this->BelongsToMany(Tenant::class)->using(GroupUserTenant::class)
            ->withPivot('user_id', 'data')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(256)
                    ->height(256);
            });
    }
}
