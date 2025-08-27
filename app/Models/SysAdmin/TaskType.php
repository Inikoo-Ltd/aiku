<?php

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TaskType withoutTrashed()
 * @mixin \Eloquent
 */
class TaskType extends Model
{
    use HasSlug;
    use SoftDeletes;

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
