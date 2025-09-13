<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:35:24 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property string|null $organisation_slug
 * @property int|null $shop_id
 * @property string|null $shop_slug
 * @property int|null $fulfilment_id
 * @property string|null $fulfilment_slug
 * @property int|null $warehouse_id
 * @property string|null $warehouse_slug
 * @property int|null $website_id
 * @property string|null $website_slug
 * @property int|null $customer_id
 * @property string|null $customer_slug
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string|null $keyword
 * @property string|null $keyword_2
 * @property string|null $haystack_tier_1
 * @property string|null $haystack_tier_2
 * @property string|null $haystack_tier_3
 * @property string $status
 * @property float $weight
 * @property string|null $date
 * @property array<array-key, mixed> $sections
 * @property array<array-key, mixed> $permissions
 * @property array<array-key, mixed> $result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent|null $model
 * @method static Builder<static>|UniversalSearch newModelQuery()
 * @method static Builder<static>|UniversalSearch newQuery()
 * @method static Builder<static>|UniversalSearch query()
 * @mixin \Eloquent
 */
class UniversalSearch extends Model
{
    use Searchable;

    protected $casts = [
        'sections'    => 'array',
        'permissions' => 'array',
        'result'      => 'array',
    ];

    protected $attributes = [
        'sections'    => '{}',
        'permissions' => '{}',
        'result'      => '{}',
    ];

    protected $guarded = [];

    protected $table = 'universal_searches';

    public function searchableAs(): string
    {
        return config('elasticsearch.index_prefix').'search';
    }

    public function toSearchableArray(): array
    {
        return Arr::only($this->toArray(), [
            'group_id',
            'organisation_id',
            'organisation_slug',
            'shop_id',
            'shop_slug',
            'fulfilment_id',
            'fulfilment_slug',
            'warehouse_id',
            'warehouse_slug',
            'website_id',
            'website_slug',
            'customer_id',
            'customer_slug',
            'haystack_tier_1',
            'haystack_tier_2',
            'haystack_tier_3',
            'status',
            'weight',
            'date',
            'sections',
            'permissions',
            'model_type',
        ]);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }


}
