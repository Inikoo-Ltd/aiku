<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 14:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $web_block_type_id
 * @property string|null $checksum
 * @property object $layout
 * @property object $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $migration_checksum
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $collections
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\ExternalLink> $externalLinks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebBlockHistory> $histories
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductCategory> $productCategories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 * @property-read \App\Models\Web\WebBlockType $webBlockType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\Webpage> $webpages
 * @method static Builder<static>|WebBlock newModelQuery()
 * @method static Builder<static>|WebBlock newQuery()
 * @method static Builder<static>|WebBlock query()
 * @mixin \Eloquent
 */
class WebBlock extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $casts = [
        'layout' => 'object',
        'data'  => 'object',
    ];

    protected $attributes = [
        'layout' => '{}',
        'data'   => '{}',
    ];

    protected $guarded = [];


    public function webBlockType(): BelongsTo
    {
        return $this->belongsTo(WebBlockType::class);
    }

    public function images(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_media');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'model', 'web_block_has_models')->withTimestamps();
    }

    public function productCategories(): MorphToMany
    {
        return $this->morphedByMany(ProductCategory::class, 'model', 'web_block_has_models')->withTimestamps();
    }

    public function collections(): MorphToMany
    {
        return $this->morphedByMany(Collection::class, 'model', 'web_block_has_models')->withTimestamps();
    }

    public function externalLinks()
    {
        return $this->belongsToMany(ExternalLink::class, 'web_block_has_external_link')
                    ->withPivot('group_id', 'organisation_id', 'webpage_id', 'website_id', 'webpage_id', 'show')
                    ->withTimestamps();
    }

    public function webpages(): MorphToMany
    {
        return $this->morphedByMany(Webpage::class, 'model', 'model_has_web_blocks')
            ->orderByPivot('position')
            ->withPivot('id', 'position', 'show', 'show_logged_in', 'show_logged_out')
            ->withTimestamps();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WebBlockHistory::class);
    }

}
