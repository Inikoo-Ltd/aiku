<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:32:29 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWebsite;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Web\Webpage
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int|null $parent_id
 * @property int $website_id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $slug
 * @property string $code
 * @property string $url
 * @property string $title
 * @property string|null $description
 * @property int $level
 * @property bool $is_fixed
 * @property WebpageStateEnum $state
 * @property WebpageTypeEnum $type
 * @property WebpageSubTypeEnum $sub_type
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property array<array-key, mixed> $published_layout
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $live_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $published_checksum
 * @property bool $is_dirty
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property array<array-key, mixed> $migration_data
 * @property string|null $canonical_url
 * @property bool $is_use_canonical_url
 * @property array<array-key, mixed> $seo_data
 * @property bool $allow_fetch If false changes in Aurora webpages will not be fetched
 * @property bool|null $show_in_parent
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Deployment> $deployments
 * @property-read Collection<int, \App\Models\Web\ExternalLink> $externalLinks
 * @property-read Group $group
 * @property-read Collection<int, Webpage> $linkedWebpages
 * @property-read Model|\Eloquent|null $model
 * @property-read Collection<int, ModelHasWebBlocks> $modelHasWebBlocks
 * @property-read Organisation $organisation
 * @property-read Webpage|null $parent
 * @property-read Collection<int, \App\Models\Web\Redirect> $redirects
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Collection<int, Snapshot> $snapshots
 * @property-read \App\Models\Web\WebpageStats|null $stats
 * @property-read Collection<int, \App\Models\Web\WebpageTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Snapshot|null $unpublishedSnapshot
 * @property-read Collection<int, \App\Models\Web\WebBlock> $webBlocks
 * @property-read Collection<int, Webpage> $webpages
 * @property-read \App\Models\Web\Website $website
 * @method static \Database\Factories\Web\WebpageFactory factory($count = null, $state = [])
 * @method static Builder<static>|Webpage newModelQuery()
 * @method static Builder<static>|Webpage newQuery()
 * @method static Builder<static>|Webpage onlyTrashed()
 * @method static Builder<static>|Webpage query()
 * @method static Builder<static>|Webpage withTrashed()
 * @method static Builder<static>|Webpage withoutTrashed()
 * @mixin Eloquent
 */
class Webpage extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use HasFactory;
    use HasUniversalSearch;
    use SoftDeletes;
    use InWebsite;
    use HasHistory;
    use HasImage;

    protected $casts = [
        'data'             => 'array',
        'settings'         => 'array',
        'published_layout' => 'array',
        'migration_data'   => 'array',
        'seo_data'         => 'array',
        'state'            => WebpageStateEnum::class,
        'sub_type'         => WebpageSubTypeEnum::class,
        'type'             => WebpageTypeEnum::class,
        'ready_at'         => 'datetime',
        'live_at'          => 'datetime',
        'closed_at'        => 'datetime',
        'fetched_at'       => 'datetime',
        'last_fetched_at'  => 'datetime'
    ];

    protected $attributes = [
        'data'             => '{}',
        'settings'         => '{}',
        'published_layout' => '{}',
        'seo_data'         => '{}',
        'migration_data'   => '{}'
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->code.'-'.$this->shop->code;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'websites'
        ];
    }

    protected array $auditInclude = [
        'code',
        'url',
        'state',
        'ready_at',
        'live_at',
        'closed_at',
        'sub_type',
        'type'
    ];

    public function stats(): HasOne
    {
        return $this->hasOne(WebpageStats::class);
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }

    public function unpublishedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_snapshot_id');
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(Webpage::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Webpage::class, 'parent_id');
    }

    public function modelHasWebBlocks(): HasMany
    {
        return $this->hasMany(ModelHasWebBlocks::class);
    }


    public function webBlocks(): MorphToMany
    {
        return $this->morphToMany(WebBlock::class, 'model', 'model_has_web_blocks')
            ->orderByPivot('position')
            ->withPivot('id', 'position', 'show', 'show_logged_in', 'show_logged_out')
            ->withTimestamps();
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkedWebpages(): BelongsToMany
    {
        return $this->belongsToMany(Webpage::class, "webpage_has_linked_webpages", 'webpage_id', 'child_id')
            ->withTimestamps()->withPivot('model_type', 'model_id', 'scope');
    }


    public function getFullUrl(): string
    {
        return 'https://'.$this->website->domain.'/'.$this->url;
    }

    public function externalLinks()
    {
        return $this->belongsToMany(ExternalLink::class, 'web_block_has_external_link')
                    ->withPivot('website_id', 'web_block_id', 'show')
                    ->withTimestamps();
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(WebpageTimeSeries::class);
    }

    /**
     * Get the redirects associated with the webpage.
     *
     * This relationship retrieves all redirect rules that point to this webpage.
     * Each redirect maps an external URL or path to this webpage, allowing
     * multiple entry points to reach the same content.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

}
