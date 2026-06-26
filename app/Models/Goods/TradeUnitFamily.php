<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Dec 2024 12:06:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Goods;

use App\Enums\Catalogue\HealthRankEnum;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasSearch;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property array<array-key, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property HealthRankEnum|null $health_rank
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Brand> $brands
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \App\Models\Goods\TradeUnitFamilyStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\TradeUnitFamilyTimeSeries> $timeSeries
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Goods\TradeUnit> $tradeUnits
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TradeUnitFamily withoutTrashed()
 * @mixin \Eloquent
 */
class TradeUnitFamily extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use InGroup;
    use HasHistory;
    use HasFactory;
    use HasAttachments;
    use HasSearch;

    protected $table = 'trade_unit_families';

    protected $casts = [
        'data'        => 'array',
        'health_rank' => HealthRankEnum::class,
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function searchIndexShouldBeUpdated(): bool
    {
        return $this->wasRecentlyCreated
            || $this->wasChanged([
                'code',
                'name',
                'description',
                'created_at'
            ]);
    }

    public function toSearchableArray(): array
    {
        return [

            'id'          => (string)$this->id,
            'code'        => $this->code,
            'name'        => (string)$this->name,
            'description' => (string)$this->description,
            'created_at'  => is_string($this->created_at) ? Carbon::parse($this->created_at)->timestamp : $this->created_at->timestamp,
        ];
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
        'description'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tradeUnits(): HasMany
    {
        return $this->hasMany(TradeUnit::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(TradeUnitFamilyStats::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(TradeUnitFamilyTimeSeries::class);
    }

    public function brands(): MorphToMany
    {
        return $this->morphToMany(Brand::class, 'model', 'model_has_brands');
    }

    public function brand(): ?Brand
    {
        /** @var Brand $brand */
        $brand = $this->brands()->first();

        return $brand;
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(
            Tag::class,
            'model',
            'model_has_tags'
        )->withTimestamps();
    }
}
