<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $crawl_id
 * @property int|null $webpage_id
 * @property string $url
 * @property string $url_hash
 * @property int $depth
 * @property int|null $status_code
 * @property string|null $redirect_to
 * @property int $redirect_hops
 * @property int|null $response_ms
 * @property int|null $bytes
 * @property string|null $content_type
 * @property string|null $title
 * @property string|null $meta_description
 * @property string|null $canonical
 * @property string|null $robots_meta
 * @property int $h1_count
 * @property int $images_without_alt
 * @property int $inlinks
 * @property bool $is_in_sitemap
 * @property bool $is_indexable
 * @property string|null $fetch_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Web\Crawl $crawl
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\CrawlIssue> $issues
 * @property-read \App\Models\Web\Webpage|null $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlPage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlPage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlPage query()
 * @mixin \Eloquent
 */
class CrawlPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_in_sitemap' => 'boolean',
            'is_indexable'  => 'boolean',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CrawlIssue::class);
    }
}
