<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-16h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Dispatching;

use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $name
 * @property int $stock
 * @property string|null $dimension
 * @property int $height
 * @property int $width
 * @property int $depth
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Box newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Box newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Box query()
 * @mixin \Eloquent
 */
class Box extends Model implements Auditable
{
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InOrganisation;

    protected $guarded = [];

    protected array $auditInclude = [
        'name',
        'dimension',
        'net_amount',
        'height',
        'width',
        'depth',
        'stock',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
