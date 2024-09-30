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
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Database\Factories\SysAdmin\AdminFactory factory($count = null, $state = [])
 * @method static Builder|Admin newModelQuery()
 * @method static Builder|Admin newQuery()
 * @method static Builder|Admin onlyTrashed()
 * @method static Builder|Admin query()
 * @method static Builder|Admin withTrashed()
 * @method static Builder|Admin withoutTrashed()
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
            ->slugsShouldBeNoLongerThan(8);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
