<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Aug 2024 11:54:18 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Ordering\Order;
use App\Models\Traits\HasImage;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property PlatformTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $image_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Order> $orders
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Dropshipping\Portfolio> $portfolios
 * @property-read \App\Models\Dropshipping\PlatformStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Platform query()
 * @mixin \Eloquent
 */
class Platform extends Model
{
    use HasSlug;
    use InGroup;
    use HasImage;

    protected $casts = [
        'type' => PlatformTypeEnum::class
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PlatformStats::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

}
