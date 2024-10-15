<?php
/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-13h-28m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Catalogue;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property ProductCategoryTypeEnum $type
 * @property ProductCategoryStateEnum $state
 * @property int|null $master_department_id
 * @property int|null $master_sub_department_id
 * @property int|null $master_parent_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property int|null $image_id
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $discontinuing_at
 * @property \Illuminate\Support\Carbon|null $discontinued_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int $master_shop_id
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, MasterProductCategory> $children
 * @property-read MasterProductCategory|null $department
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read MasterProductCategory|null $parent
 * @property-read MasterProductCategory|null $subDepartment
 * @property-read LaravelCollection<int, MasterProductCategory> $subDepartments
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|MasterProductCategory withoutTrashed()
 * @mixin \Eloquent
 */
class MasterProductCategory extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasImage;

    protected $guarded = [];

    protected $casts = [
        'data'             => 'array',
        'state'            => ProductCategoryStateEnum::class,
        'type'             => ProductCategoryTypeEnum::class,
        'activated_at'     => 'datetime',
        'discontinuing_at' => 'datetime',
        'discontinued_at'  => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function generateTags(): array
    {
        return [
            'catalogue',
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
            ->generateSlugsFrom(function () {
                return $this->code.'-'.$this->group->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_department_id');
    }

    public function subDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_sub_department_id');
    }

    public function subDepartments(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class, 'master_department_id');
    }


    public function parent(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class, 'master_parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class, 'master_parent_id');
    }

    public function families(): LaravelCollection
    {
        return $this->children()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }


    public function masterProducts(): HasMany|null
    {
        return match ($this->type) {
            ProductCategoryTypeEnum::DEPARTMENT => $this->hasMany(MasterProduct::class, 'master_department_id'),
            ProductCategoryTypeEnum::FAMILY     => $this->hasMany(MasterProduct::class, 'master_family_id'),
            default                             => null
        };
    }
}
