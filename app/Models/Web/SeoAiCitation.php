<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A source an AI answer cites, in the order the answer lists them, tied to a competitor or to one of
 * our webpages when the domain is theirs or ours.
 *
 * @property int $id
 * @property int $answer_id
 * @property int $position
 * @property string $url
 * @property string $domain
 * @property string|null $title
 * @property int|null $competitor_id
 * @property int|null $website_id
 * @property int|null $webpage_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SeoAiAnswer $answer
 * @property-read Webpage|null $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiCitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiCitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiCitation query()
 * @mixin \Eloquent
 */
class SeoAiCitation extends Model
{
    protected $guarded = [];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(SeoAiAnswer::class, 'answer_id');
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }
}
