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
 * @property-read Model|\Eloquent $model
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
