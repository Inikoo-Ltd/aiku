<?php

/*
 * author Arya Permana - Kirin
 * created on 19-11-2024-16h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Ordering;

use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property bool $is_active
 * @property SalesChannelTypeEnum $type
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property bool $is_seeded
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property array<array-key, mixed>|null $sources
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Ordering\SalesChannelStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesChannel withoutTrashed()
 * @mixin \Eloquent
 */
class SalesChannel extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasHistory;
    use InGroup;

    protected $casts = [
        'type'            => SalesChannelTypeEnum::class,
        'data'            => 'array',
        'is_active'       => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'sources'         => 'array'
    ];

    protected $attributes = [
        'data'    => '{}',
        'sources' => '{}'
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['ordering'];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'is_active'
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(SalesChannelStats::class);
    }
}
