<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:12:24 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SysAdmin\Admin
 *
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $email
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Database\Factories\SysAdmin\AdminFactory factory($count = null, $state = [])
 * @method static Builder<static>|Admin newModelQuery()
 * @method static Builder<static>|Admin newQuery()
 * @method static Builder<static>|Admin onlyTrashed()
 * @method static Builder<static>|Admin query()
 * @method static Builder<static>|Admin withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Admin withoutTrashed()
 * @mixin Eloquent
 */
class Admin extends Model
{
    use HasSlug;
    use SoftDeletes;
    use HasFactory;

    protected $guarded = [];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
