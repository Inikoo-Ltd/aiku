<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:32:29 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Website\WebsiteCloudflareStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Media;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Web\Website
 *
 * @property int $id
 * @property string $slug
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property WebsiteTypeEnum $type
 * @property string $code
 * @property string $name
 * @property WebsiteStateEnum $state
 * @property bool $status
 * @property string $domain
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $structure
 * @property array<array-key, mixed> $layout
 * @property array<array-key, mixed> $published_layout
 * @property int|null $unpublished_header_snapshot_id
 * @property int|null $live_header_snapshot_id
 * @property string|null $published_header_checksum
 * @property bool $header_is_dirty
 * @property int|null $unpublished_footer_snapshot_id
 * @property int|null $live_footer_snapshot_id
 * @property string|null $published_footer_checksum
 * @property bool $footer_is_dirty
 * @property int|null $current_layout_id
 * @property int|null $logo_id
 * @property \Illuminate\Support\Carbon|null $launched_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $storefront_id
 * @property int|null $catalogue_id
 * @property int|null $products_id
 * @property int|null $login_id
 * @property int|null $register_id
 * @property int|null $basket_id
 * @property int|null $checkout_id
 * @property int|null $call_back_id
 * @property int|null $appointment_id
 * @property int|null $pricing_id
 * @property string|null $cloudflare_id
 * @property WebsiteCloudflareStatusEnum|null $cloudflare_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property int|null $favicon_id
 * @property int|null $unpublished_menu_snapshot_id
 * @property int|null $live_menu_snapshot_id
 * @property string|null $published_menu_checksum
 * @property int|null $unpublished_department_snapshot_id
 * @property int|null $live_department_snapshot_id
 * @property string|null $published_department_checksum
 * @property int|null $unpublished_sub_department_snapshot_id
 * @property int|null $live_sub_department_snapshot_id
 * @property string|null $published_sub_department_checksum
 * @property int|null $unpublished_family_snapshot_id
 * @property int|null $live_family_snapshot_id
 * @property string|null $published_family_checksum
 * @property int|null $unpublished_product_snapshot_id
 * @property int|null $live_product_snapshot_id
 * @property string|null $published_product_checksum
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, Deployment> $deployments
 * @property-read Collection<int, \App\Models\Web\ExternalLink> $externalLinks
 * @property-read Media|null $favicon
 * @property-read Group $group
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read Snapshot|null $liveSnapshot
 * @property-read Media|null $logo
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read Organisation $organisation
 * @property-read Collection<int, \App\Models\Web\Redirect> $redirects
 * @property-read Shop $shop
 * @property-read Collection<int, Snapshot> $snapshots
 * @property-read \App\Models\Web\Webpage|null $storefront
 * @property-read Collection<int, \App\Models\Web\WebsiteTimeSeries> $timeSeries
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Snapshot|null $unpublishedDepartmentSnapshot
 * @property-read Snapshot|null $unpublishedFamilySnapshot
 * @property-read Snapshot|null $unpublishedFooterSnapshot
 * @property-read Snapshot|null $unpublishedHeaderSnapshot
 * @property-read Snapshot|null $unpublishedMenuSnapshot
 * @property-read Snapshot|null $unpublishedProductSnapshot
 * @property-read Snapshot|null $unpublishedSubDepartmentSnapshot
 * @property-read Collection<int, \App\Models\Web\WebBlock> $webBlocks
 * @property-read \App\Models\Web\WebsiteStats|null $webStats
 * @property-read Collection<int, \App\Models\Web\Webpage> $webpages
 * @method static \Database\Factories\Web\WebsiteFactory factory($count = null, $state = [])
 * @method static Builder<static>|Website newModelQuery()
 * @method static Builder<static>|Website newQuery()
 * @method static Builder<static>|Website onlyTrashed()
 * @method static Builder<static>|Website query()
 * @method static Builder<static>|Website withTrashed()
 * @method static Builder<static>|Website withoutTrashed()
 * @mixin Eloquent
 */
class Website extends Model implements Auditable, HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasHistory;
    use HasUniversalSearch;
    use HasFactory;
    use InShop;
    use InteractsWithMedia;

    protected $casts = [
        'type'              => WebsiteTypeEnum::class,
        'data'              => 'array',
        'settings'          => 'array',
        'structure'         => 'array',
        'layout'            => 'array',
        'published_layout'  => 'array',
        'state'             => WebsiteStateEnum::class,
        'status'            => 'boolean',
        'cloudflare_status' => WebsiteCloudflareStatusEnum::class,
        'launched_at'       => 'datetime',
        'closed_at'         => 'datetime',
        'fetched_at'        => 'datetime',
        'last_fetched_at'   => 'datetime',

    ];

    protected $attributes = [
        'data'             => '{}',
        'settings'         => '{}',
        'structure'        => '{}',
        'layout'           => '{}',
        'published_layout' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'websites'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'domain',
        'state',
        'status',
    ];


    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(Webpage::class);
    }

    public function webStats(): HasOne
    {
        return $this->hasOne(WebsiteStats::class);
    }

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Webpage::class, 'storefront_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    public function favicon(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_id');
    }

    public function imageSources($width = 0, $height = 0)
    {
        if ($this->logo) {
            $avatarThumbnail = $this->logo->getImage()->resize($width, $height);
            return GetPictureSources::run($avatarThumbnail);
        }
        return null;
    }

    public function faviconSources($width = 0, $height = 0)
    {
        if ($this->favicon) {
            $avatarThumbnail = $this->favicon->getImage()->resize($width, $height);
            return GetPictureSources::run($avatarThumbnail);
        }
        return null;
    }



    public function images(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'model', 'model_has_media');
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }

    public function unpublishedHeaderSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_header_snapshot_id');
    }

    public function unpublishedFooterSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_footer_snapshot_id');
    }

    public function unpublishedMenuSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_menu_snapshot_id');
    }

    public function unpublishedDepartmentSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_department_snapshot_id');
    }

    public function unpublishedSubDepartmentSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_sub_department_snapshot_id');
    }

    public function unpublishedFamilySnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_family_snapshot_id');
    }

    public function unpublishedProductSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_product_snapshot_id');
    }

    public function liveSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'live_snapshot_id');
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }

    public function getUrl(): string
    {
        $scheme = app()->environment('production') ? 'https' : 'http';

        return $scheme.'://'.$this->domain;
    }

    public function webBlocks(): MorphMany
    {
        return $this->morphMany(WebBlock::class, 'model');
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

    public function externalLinks()
    {
        return $this->belongsToMany(ExternalLink::class, 'web_block_has_external_link')
                    ->withPivot('webpage_id', 'web_block_id', 'show')
                    ->withTimestamps();
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(WebsiteTimeSeries::class);
    }

    public function getFullUrl(): string
    {
        return match (app()->environment()) {
            'production' => 'https://'.$this->domain . '/app',
            'staging' => 'https://canary.'.$this->domain . '/app',
            default => match ($this->shop->type) {
                ShopTypeEnum::DROPSHIPPING => 'https://ds.test/app',
                default => 'https://fulfilment.test/app'
            }
        };
    }

}
