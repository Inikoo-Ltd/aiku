<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 10:58:08 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\DevOps;

use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $slug
 * @property bool $active
 * @property string $name
 * @property string $ip
 * @property bool $master
 * @property bool $slave
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server query()
 * @mixin \Eloquent
 */
class Server extends Model
{
    use HasSlug;

    protected $casts = [
        'data'            => 'array',
        'active'          => 'boolean',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
