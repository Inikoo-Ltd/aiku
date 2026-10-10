<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One answer of an AI assistant to a prompt on one day: whether it names our brand (and in which
 * place among the brands it names), whether it cites our website, and the same for each competitor.
 *
 * @property int $id
 * @property int $prompt_id
 * @property string $platform
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $model
 * @property string|null $answer
 * @property bool $is_mentioned
 * @property int|null $brand_position
 * @property bool $is_cited
 * @property array<int, string>|null $brands
 * @property array<int, array{competitor_id: int, is_mentioned: bool, is_cited: bool}>|null $competitors
 * @property array<int, string>|null $fan_out_queries
 * @property string|null $check_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SeoAiPrompt $prompt
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SeoAiCitation> $citations
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoAiAnswer query()
 * @mixin \Eloquent
 */
class SeoAiAnswer extends Model
{
    public const string CHAT_GPT = 'chat_gpt';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'is_mentioned'    => 'boolean',
            'is_cited'        => 'boolean',
            'brands'          => 'array',
            'competitors'     => 'array',
            'fan_out_queries' => 'array',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(SeoAiPrompt::class, 'prompt_id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(SeoAiCitation::class, 'answer_id');
    }
}
