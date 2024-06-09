<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jun 2023 01:53:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\ClockingMachine
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $workplace_id
 * @property string $slug
 * @property string $name
 * @property string $type
 * @property ClockingMachineStatusEnum $status
 * @property string|null $device_name
 * @property string|null $device_uuid
 * @property string|null $qr_code
 * @property array $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, \App\Models\HumanResources\Clocking> $clockings
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\HumanResources\ClockingMachineStats|null $stats
 * @property-read Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read UniversalSearch|null $universalSearch
 * @property-read \App\Models\HumanResources\Workplace $workplace
 * @method static Builder|ClockingMachine newModelQuery()
 * @method static Builder|ClockingMachine newQuery()
 * @method static Builder|ClockingMachine onlyTrashed()
 * @method static Builder|ClockingMachine query()
 * @method static Builder|ClockingMachine withTrashed()
 * @method static Builder|ClockingMachine withoutTrashed()
 * @mixin Eloquent
 */
class ClockingMachine extends Authenticatable implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InOrganisation;
    use HasApiTokens;

    protected $casts = [
        'data'        => 'array',
        'status'      => ClockingMachineStatusEnum::class,
    ];

    protected $attributes = [
        'data'        => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function clockings(): HasMany
    {
        return $this->hasMany(Clocking::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ClockingMachineStats::class);
    }

}
