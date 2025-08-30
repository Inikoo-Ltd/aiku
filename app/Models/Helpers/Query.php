<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Oct 2023 14:55:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Models\CRM\Customer;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Helpers\Query
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $name
 * @property string $model
 * @property bool $is_static
 * @property array<array-key, mixed> $constrains
 * @property array<array-key, mixed> $compiled_constrains
 * @property bool $has_arguments
 * @property string|null $seed_code
 * @property int|null $number_items
 * @property string|null $counted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property array<array-key, mixed> $source_constrains
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $customers
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Query withoutTrashed()
 * @mixin \Eloquent
 */
class Query extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasSlug;
    use InShop;

    protected $casts = [
        'constrains'          => 'array',
        'compiled_constrains' => 'array',
        'informatics'         => 'array',
        'source_constrains'   => 'array',
        'is_seeded'           => 'boolean',
        'is_static'           => 'boolean',
        'has_arguments'       => 'boolean'
    ];

    protected $attributes = [
        'constrains'          => '{}',
        'compiled_constrains' => '{}',
        'source_constrains'   => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {

        if (in_array($this->model, ['Prospect','Customer'])) {
            return [
                'crm'
            ];
        }

        return [
            'helpers'
        ];


    }


    protected array $auditInclude = [
        'name',
        'model',
        'is_static',
        'constrains'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                $slug = $this->name;
                if ($this->shop) {
                    $slug .= '-'.$this->shop->slug;
                } elseif ($this->organisation) {
                    $slug .= '-'.$this->organisation->slug;
                } else {
                    $slug .= '-'.$this->group->slug;
                }
                return $slug;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }


    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'model', 'query_has_models')->withTimestamps();
    }


}
