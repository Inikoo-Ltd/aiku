<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 10:07:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Payments\PaymentServiceProvider
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property PaymentServiceProviderTypeEnum $type
 * @property string $code
 * @property string $name
 * @property string $state
 * @property array<array-key, mixed> $data
 * @property string|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, \App\Models\Accounting\PaymentAccount> $accounts
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Collection<int, \App\Models\Accounting\OrgPaymentServiceProvider> $orgPaymentServiceProviders
 * @property-read Collection<int, \App\Models\Accounting\Payment> $payments
 * @property-read \App\Models\Accounting\PaymentServiceProviderStats|null $stats
 * @method static \Database\Factories\Accounting\PaymentServiceProviderFactory factory($count = null, $state = [])
 * @method static Builder<static>|PaymentServiceProvider newModelQuery()
 * @method static Builder<static>|PaymentServiceProvider newQuery()
 * @method static Builder<static>|PaymentServiceProvider onlyTrashed()
 * @method static Builder<static>|PaymentServiceProvider query()
 * @method static Builder<static>|PaymentServiceProvider withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PaymentServiceProvider withoutTrashed()
 * @mixin Eloquent
 */
class PaymentServiceProvider extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasHistory;
    use HasFactory;
    use InteractsWithMedia;

    protected $casts = [
        'data' => 'array',
        'type' => PaymentServiceProviderTypeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'accounting'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function orgPaymentServiceProviders(): HasMany
    {
        return $this->hasMany(OrgPaymentServiceProvider::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PaymentAccount::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PaymentServiceProviderStats::class);
    }


    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
