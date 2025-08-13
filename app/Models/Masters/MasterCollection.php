<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Masters;

use App\Enums\Catalogue\MasterCollection\MasterCollectionProductStatusEnum;
use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $master_shop_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $image_id
 * @property MasterCollectionStateEnum $state
 * @property MasterCollectionProductStatusEnum $products_status
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategory> $departments
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read LaravelCollection<int, MasterCollection> $masterCollections
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategory> $masterFamilies
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterAsset> $masterProducts
 * @property-read \App\Models\Masters\MasterShop $masterShop
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Masters\MasterCollectionOrderingStats|null $orderingStats
 * @property-read \App\Models\Masters\MasterCollectionSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read \App\Models\Masters\MasterCollectionStats|null $stats
 * @property-read LaravelCollection<int, \App\Models\Masters\MasterProductCategory> $subDepartments
 * @property-read mixed $translations
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection withoutTrashed()
 * @mixin \Eloquent
 */
class MasterCollection extends Model implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasHistory;
    use HasImage;
    use HasUniversalSearch;
    use InGroup;
    use HasTranslations;

    public array $translatable = ['name_i8n', 'description_i8n', 'description_title_i8n', 'description_extra_i8n'];

    protected $casts = [
        'data'   => 'array',
        'status' => 'boolean',
        'state'          => MasterCollectionStateEnum::class,
        'products_status' => MasterCollectionProductStatusEnum::class,
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
            'goods'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'description',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }
    public function stats(): HasOne
    {
        return $this->hasOne(MasterCollectionStats::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(MasterCollectionSalesIntervals::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(MasterCollectionOrderingStats::class);
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }

    // Warning this includes both direct products and products in families
    public function masterProducts(): MorphToMany
    {
        return $this->morphedByMany(MasterAsset::class, 'model', 'model_has_master_collections')
            ->withTimestamps()->withPivot('type');
    }

    public function masterFamilies(): MorphToMany
    {
        return $this->morphedByMany(MasterProductCategory::class, 'model', 'model_has_master_collections')
            ->withTimestamps();
    }

    public function masterCollections(): MorphToMany
    {
        return $this->morphedByMany(MasterCollection::class, 'model', 'model_has_master_collections')
            ->withTimestamps();
    }

    public function departments(): MorphToMany
    {
        return $this->morphedByMany(MasterProductCategory::class, 'model', 'model_has_master_collections')
            ->wherePivot('type', 'master_department')
            ->withTimestamps();
    }

    public function subDepartments(): MorphToMany
    {
        return $this->morphedByMany(MasterProductCategory::class, 'model', 'model_has_master_collections')
            ->wherePivot('type', 'master_sub_department')
            ->withTimestamps();
    }

}
