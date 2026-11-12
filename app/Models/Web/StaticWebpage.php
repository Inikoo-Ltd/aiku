<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $static_website_id
 * @property string $path
 * @property string|null $title
 * @property string|null $description
 * @property string|null $html
 * @property string|null $source_url
 * @property \Illuminate\Support\Carbon|null $crawled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StaticWebsite $staticWebsite
 */
class StaticWebpage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }

    public function staticWebsite(): BelongsTo
    {
        return $this->belongsTo(StaticWebsite::class);
    }
}
