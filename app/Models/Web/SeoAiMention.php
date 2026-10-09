<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;

/**
 * How often AI answers in DataForSEO's LLM Mentions database mention a domain, ours (no competitor)
 * or a competitor's, for one platform and market, as fetched on one date.
 *
 * @property int $id
 * @property int $shop_id
 * @property \Illuminate\Support\Carbon $date
 * @property string $platform
 * @property int $location_code
 * @property string $language_code
 * @property string $domain
 * @property int|null $competitor_id
 * @property int $mentions
 * @property int $ai_search_volume
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiMention newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiMention newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiMention query()
 * @mixin \Eloquent
 */
class SeoAiMention extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
