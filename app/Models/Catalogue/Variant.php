<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Dec 2025 21:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Models\Masters\MasterVariant;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $family_id
 * @property int|null $sub_department_id
 * @property int|null $department_id
 * @property string $code
 * @property int|null $leader_id
 * @property int $number_minions
 * @property int $number_dimensions
 * @property int $number_used_slots
 * @property int $number_used_slots_for_sale
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int $master_variant_id
 * @property string $slug
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Catalogue\ProductCategory|null $department
 * @property-read \App\Models\Catalogue\ProductCategory|null $family
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \App\Models\Catalogue\Product|null $leaderProduct
 * @property-read MasterVariant|null $masterVariant
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\VariantSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Catalogue\VariantSalesOrderingIntervals|null $salesOrderingIntervals
 * @property-read \App\Models\Catalogue\VariantSalesOrderingStats|null $salesOrderingStats
 * @property-read \App\Models\Catalogue\VariantSalesStats|null $salesStats
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Catalogue\ProductCategory|null $subDepartment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\VariantTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Variant withoutTrashed()
 * @mixin \Eloquent
 */
class Variant extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasImage;
    use InShop;

    protected $guarded = [];
    protected $casts = [
        'data'      => 'array',
    ];

    public function generateTags(): array
    {
        return [
            'goods',
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'department_id');
    }

    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'sub_department_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'family_id');
    }

    public function masterVariant(): HasOne
    {
        return $this->hasOne(MasterVariant::class, 'id', 'master_variant_id');
    }

    public function allProduct(): HasMany
    {
        return $this->hasMany(Product::class, 'variant_id');
    }

    public function leaderProduct(): HasOne
    {
        return $this->hasOne(Product::class, 'id', 'leader_id');
    }

    public function minionProduct(): HasMany
    {        
        return $this->hasMany(Product::class, 'variant_id')
            ->where('is_variant_leader', false);
    }

    public function salesStats(): HasOne
    {
        return $this->hasOne(VariantSalesStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(VariantSalesIntervals::class);
    }

    public function salesOrderingStats(): HasOne
    {
        return $this->hasOne(VariantSalesOrderingStats::class);
    }

    public function salesOrderingIntervals(): HasOne
    {
        return $this->hasOne(VariantSalesOrderingIntervals::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(VariantTimeSeries::class);
    }
}
