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
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
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
 * @property string|null $model_id
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
 * @property array $published_layout
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $live_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property string|null $published_checksum
 * @property bool $is_dirty
 * @property array $data
 * @property array $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property array $migration_data
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Webpage> $children
 * @property-read Collection<int, Deployment> $deployments
 * @property-read Collection<int, \App\Models\Web\ExternalLink> $externalLinks
 * @property-read Group $group
 * @property-read Model|\Eloquent|null $model
 * @property-read Collection<int, ModelHasWebBlocks> $modelHasWebBlocks
 * @property-read Organisation $organisation
 * @property-read Webpage|null $parent
 * @property-read Collection<int, \App\Models\Web\Redirect> $redirects
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Collection<int, Snapshot> $snapshots
 * @property-read \App\Models\Web\WebpageStats|null $stats
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
class Webpage extends Model implements Auditable
{
    use HasSlug;
    use HasFactory;
    use HasUniversalSearch;
    use SoftDeletes;
    use InShop;
    use HasHistory;

    protected $casts = [
        'data'             => 'array',
        'settings'         => 'array',
        'published_layout' => 'array',
        'migration_data'   => 'array',
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
            ->slugsShouldBeNoLongerThan(64);
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

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
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

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Webpage::class, "webpage_has_children", 'webpage_id', 'child_id');
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

    public function getChildrenRecursively()
    {
        return $this->webpages->map(function ($child) {
            return [
                'slug'     => $child->slug,
                'name'     => $child->code,
                'children' => $child->getChildrenRecursively()
            ];
        });
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



}
