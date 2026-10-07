<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 12:01:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $website_id
 * @property CrawlTriggerEnum $trigger
 * @property CrawlStateEnum $state
 * @property bool $running
 * @property CrawlTypeEnum $type
 * @property string|null $finish_reason
 * @property \Illuminate\Support\Carbon|null $start_at
 * @property \Illuminate\Support\Carbon|null $end_at
 * @property int $urls_processed
 * @property int $urls_found
 * @property int $depth
 * @property int $concurrency
 * @property bool $should_stop
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_seeder
 * @property int|null $max_pages
 * @property string|null $health_score
 * @property int $pages_with_errors
 * @property int $number_errors
 * @property int $number_warnings
 * @property int $number_notices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\CrawlIssue> $issues
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\CrawlPage> $pages
 * @property-read \App\Models\Web\Website|null $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crawl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crawl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Crawl query()
 * @mixin \Eloquent
 */
class Crawl extends Model
{
    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'state'    => CrawlStateEnum::class,
        'type'     => CrawlTypeEnum::class,
        'trigger'  => CrawlTriggerEnum::class,
        'is_seeder' => 'boolean'
    ];

    protected $guarded = [];


    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CrawlPage::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CrawlIssue::class);
    }
}
