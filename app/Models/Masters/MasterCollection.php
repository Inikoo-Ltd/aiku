<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\Masters;

use App\Models\SysAdmin\Group;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string|null $description
 * @property bool $status
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MasterCollection withoutTrashed()
 * @mixin \Eloquent
 */
class MasterCollection extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasHistory;
    use InGroup;

    protected $casts = [
        'data'   => 'array',
        'status' => 'boolean',
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
}
