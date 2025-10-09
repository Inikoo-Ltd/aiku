<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 23:23:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $customer_id
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
 * @property array<array-key, mixed> $web_users
 * @property array<array-key, mixed> $result
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaSearch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaSearch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RetinaSearch query()
 * @mixin \Eloquent
 */
class RetinaSearch extends Model
{
    use Searchable;

    protected $casts = [
        'sections'    => 'array',
        'permissions' => 'array',
        'result'      => 'array',
        'web_users'   => 'array',
    ];

    protected $attributes = [
        'sections'    => '{}',
        'permissions' => '{}',
        'result'      => '{}',
        'web_users'   => '{}',
    ];

    protected $guarded = [];

    public function searchableAs(): string
    {
        return config('elasticsearch.index_prefix').'retina_search';
    }

    public function toSearchableArray(): array
    {
        return Arr::only($this->toArray(), [
            'group_id',
            'organisation_id',
            'customer_id',
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

}
