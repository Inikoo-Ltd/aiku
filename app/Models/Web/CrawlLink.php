<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Enums\Web\Crawl\CrawlLinkKindEnum;
use App\Enums\Web\Crawl\CrawlLinkPositionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlLink extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind'        => CrawlLinkKindEnum::class,
            'position'    => CrawlLinkPositionEnum::class,
            'is_nofollow' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }
}
