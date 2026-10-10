<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * A weekly backlink snapshot of one domain, ours or a competitor's, from DataForSEO. `rank` is
 * DataForSEO's 0 to 100 domain rank, not Moz DA or the Semrush Authority Score.
 *
 * @property int $id
 * @property string $domain
 * @property \Illuminate\Support\Carbon $date
 * @property int|null $rank
 * @property int $backlinks
 * @property int $referring_domains
 * @property int $referring_main_domains
 * @property int $broken_backlinks
 * @property int $broken_pages
 * @property int|null $spam_score
 * @property int|null $new_referring_domains
 * @property int|null $lost_referring_domains
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklinkSummary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklinkSummary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoBacklinkSummary query()
 * @mixin \Eloquent
 */
class SeoBacklinkSummary extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
