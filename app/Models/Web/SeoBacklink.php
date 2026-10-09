<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One link from another page to one of our websites. `target_path` is stored the way
 * `website_not_found_paths.path` is, so broken links join the Missing pages list.
 *
 * @property int $id
 * @property int $website_id
 * @property string $source_url
 * @property string $source_url_hash
 * @property string $source_domain
 * @property string|null $source_title
 * @property int|null $domain_rank
 * @property int|null $page_rank
 * @property bool $is_own_website
 * @property string $target_url
 * @property string $target_url_hash
 * @property string $target_path
 * @property int|null $target_webpage_id
 * @property string|null $anchor
 * @property string|null $link_type
 * @property bool $is_dofollow
 * @property bool $is_broken
 * @property int|null $target_status_code
 * @property \Illuminate\Support\Carbon|null $first_seen
 * @property \Illuminate\Support\Carbon|null $last_seen
 * @property \Illuminate\Support\Carbon|null $lost_at
 * @property \Illuminate\Support\Carbon $fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Website $website
 * @property-read Webpage|null $targetWebpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklink query()
 * @mixin \Eloquent
 */
class SeoBacklink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_own_website' => 'boolean',
            'is_dofollow'    => 'boolean',
            'is_broken'      => 'boolean',
            'first_seen'     => 'datetime',
            'last_seen'      => 'datetime',
            'lost_at'        => 'datetime',
            'fetched_at'     => 'datetime',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function targetWebpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class, 'target_webpage_id');
    }
}
