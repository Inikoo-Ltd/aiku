<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 19:51:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Audits\Redactors\PasswordRedactor;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use App\Models\Traits\IsUserable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $supplier_id
 * @property string $slug
 * @property bool $is_root
 * @property bool $status
 * @property string $username
 * @property string|null $email
 * @property string|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $about
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property bool $reset_password
 * @property int $language_id
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Helpers\Language $language
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\SupplyChain\SupplierUserStats|null $stats
 * @property-read \App\Models\SupplyChain\Supplier $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Database\Factories\SupplyChain\SupplierUserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUser withoutTrashed()
 * @mixin \Eloquent
 */
class SupplierUser extends Authenticatable implements HasMedia, Auditable
{
    use IsUserable;
    use HasImage;
    use InGroup;
    use HasFactory;

    protected $casts = [
        'data'            => 'array',
        'settings'        => 'array',
        'status'          => 'boolean',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];

    protected $guarded = [
    ];

    public function generateTags(): array
    {
        return [
            'supply-chain'
        ];
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

                return str_replace('@', '_at_', $slug);
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(SupplierUserStats::class);
    }


}
