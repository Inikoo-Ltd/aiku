<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlResource extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function crawl(): BelongsTo
    {
        return $this->belongsTo(Crawl::class);
    }
}
