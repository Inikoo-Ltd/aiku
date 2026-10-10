<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question a customer could ask an AI assistant, written by the team for one shop and sent to
 * ChatGPT every week to see whether the answer names or cites us.
 *
 * @property int $id
 * @property int $shop_id
 * @property string $prompt
 * @property string $country_code
 * @property string $language_code
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Shop $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeoAiAnswer> $answers
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiPrompt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiPrompt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiPrompt query()
 * @mixin \Eloquent
 */
class SeoAiPrompt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'queued_at'   => 'datetime',
            'last_run_at' => 'date',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SeoAiAnswer::class, 'prompt_id');
    }
}
