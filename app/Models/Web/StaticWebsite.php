<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\StaticWebsite\StaticWebsiteStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * An information-only website frozen from an outside host and served by Aiku, with no shop,
 * catalogue or customers behind it.
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $name
 * @property string $domain
 * @property StaticWebsiteStateEnum $state
 * @property string|null $source_url
 * @property \Illuminate\Support\Carbon|null $crawled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StaticWebpage> $webpages
 */
class StaticWebsite extends Model
{
    use HasSlug;

    public const string ASSET_URL_PREFIX = '/_assets/';

    public const string LIVE_DOMAINS_CACHE_KEY = 'static_websites:live_domains';

    public const array ASSET_CONTENT_TYPES = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'text/javascript; charset=UTF-8',
        'jpg'   => 'image/jpeg',
        'png'   => 'image/png',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'pdf'   => 'application/pdf',
        'mp4'   => 'video/mp4',
        'webm'  => 'video/webm',
        'mp3'   => 'audio/mpeg',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::LIVE_DOMAINS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::LIVE_DOMAINS_CACHE_KEY));
    }

    /**
     * @return array<string, int> live domain => static website id
     */
    public static function liveDomains(): array
    {
        return Cache::remember(
            self::LIVE_DOMAINS_CACHE_KEY,
            600,
            fn () => self::where('state', StaticWebsiteStateEnum::LIVE)->pluck('id', 'domain')->all()
        );
    }

    public static function assetContentType(string $name): string
    {
        return self::ASSET_CONTENT_TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    public static function assetDisk(): Filesystem
    {
        return Storage::disk(config('media-library.disk_name'));
    }

    public function assetStoragePath(string $name = ''): string
    {
        return 'static-websites/'.$this->domain.($name === '' ? '' : '/'.$name);
    }

    protected function casts(): array
    {
        return [
            'state'      => StaticWebsiteStateEnum::class,
            'crawled_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('domain')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(64);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(StaticWebpage::class);
    }
}
