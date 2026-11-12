<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Enums\Web\StaticWebsite\StaticWebsiteComponentEnum;
use App\Models\Traits\HasStaticWebsiteSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part every page of an info website shares, such as the header with its menu or the footer. Pages
 * hold a marker where it goes, so one change here reaches every page.
 *
 * @property int $id
 * @property int $static_website_id
 * @property StaticWebsiteComponentEnum $code
 * @property string $html
 * @property \Illuminate\Support\Carbon|null $crawled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StaticWebsite $staticWebsite
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Snapshot> $snapshots
 */
class StaticWebsiteComponent extends Model
{
    use HasStaticWebsiteSnapshots;

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'code'       => StaticWebsiteComponentEnum::class,
            'crawled_at' => 'datetime',
        ];
    }

    public function staticWebsite(): BelongsTo
    {
        return $this->belongsTo(StaticWebsite::class);
    }

    public function wasEditedAfterImport(): bool
    {
        return $this->crawled_at && $this->updated_at && $this->updated_at->gt($this->crawled_at);
    }
}
