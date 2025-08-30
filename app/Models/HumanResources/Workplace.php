<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Jun 2023 23:30:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\HumanResources;

use App\Actions\Utils\Abbreviate;
use App\Enums\HumanResources\Workplace\WorkplaceTypeEnum;
use App\Models\Helpers\Timezone;
use App\Models\Helpers\UniversalSearch;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\HumanResources\Workplace
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property bool $status
 * @property WorkplaceTypeEnum $type
 * @property string $slug
 * @property string $name
 * @property int|null $timezone_id
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Helpers\Address|null $address
 * @property-read Collection<int, \App\Models\Helpers\Address> $addresses
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, \App\Models\HumanResources\ClockingMachine> $clockingMachines
 * @property-read Collection<int, \App\Models\HumanResources\Clocking> $clockings
 * @property-read Collection<int, \App\Models\HumanResources\Employee> $employees
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\HumanResources\WorkplaceStats|null $stats
 * @property-read Timezone|null $timezone
 * @property-read UniversalSearch|null $universalSearch
 * @method static Builder<static>|Workplace newModelQuery()
 * @method static Builder<static>|Workplace newQuery()
 * @method static Builder<static>|Workplace onlyTrashed()
 * @method static Builder<static>|Workplace query()
 * @method static Builder<static>|Workplace withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Workplace withoutTrashed()
 * @mixin Eloquent
 */
class Workplace extends Model implements Auditable
{
    use HasSlug;
    use HasUniversalSearch;
    use SoftDeletes;
    use HasHistory;
    use HasAddress;
    use HasAddresses;
    use InOrganisation;

    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
        'location' => 'array',
        'status'   => 'boolean',
        'type'     => WorkplaceTypeEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'location' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'hr'
        ];
    }

    protected array $auditInclude = [
        'name',
        'type',
    ];


    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return Abbreviate::run($this->name, digits: true, maximumLength: 8);
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }


    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }


    public function clockingMachines(): HasMany
    {
        return $this->hasMany(ClockingMachine::class);
    }

    public function clockings(): HasMany
    {
        return $this->hasMany(Clocking::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WorkplaceStats::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)->withTimestamps();
    }

}
