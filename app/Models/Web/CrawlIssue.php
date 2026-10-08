<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $crawl_id
 * @property int $crawl_page_id
 * @property CrawlIssueTypeEnum $type
 * @property CrawlIssueSeverityEnum $severity
 * @property array<array-key, mixed>|null $details
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Web\Crawl $crawl
 * @property-read \App\Models\Web\CrawlPage $crawlPage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlIssue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlIssue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CrawlIssue query()
 * @mixin \Eloquent
 */
class CrawlIssue extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'     => CrawlIssueTypeEnum::class,
            'severity' => CrawlIssueSeverityEnum::class,
            'details'  => 'array',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }

    public function crawlPage(): BelongsTo
    {
        return $this->belongsTo(CrawlPage::class);
    }
}
