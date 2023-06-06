<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:32:29 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Web\Webpage
 *
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $url
 * @property string $purpose
 * @property string $type
 * @property int $website_id
 * @property int|null $main_variant_id
 * @property mixed $data
 * @property mixed $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property int|null $source_id
 * @property-read \App\Models\Web\WebpageVariant|null $mainVariant
 * @property-read \App\Models\Web\WebpageStats|null $stats
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebpageVariant> $variants
 * @property-read \App\Models\Web\Website $website
 * @method static Builder|Webpage newModelQuery()
 * @method static Builder|Webpage newQuery()
 * @method static Builder|Webpage query()
 * @mixin \Eloquent
 */
class Webpage extends Model
{
    use UsesTenantConnection;
    use HasSlug;

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(WebpageStats::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function mainVariant(): BelongsTo
    {
        return $this->belongsTo(WebpageVariant::class, 'main_variant_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(WebpageVariant::class);
    }
}
