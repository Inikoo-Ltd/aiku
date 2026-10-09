<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Models\Web;

use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A meta title or description written by the AI gateway for a webpage. Never published by itself:
 * it waits until someone accepts it, possibly edited, or dismisses it.
 *
 * @property int $id
 * @property int $website_id
 * @property int $webpage_id
 * @property string $field
 * @property string|null $current_value
 * @property string $suggestion
 * @property string $reason
 * @property SeoContentSuggestionStateEnum $state
 * @property string|null $model
 * @property int|null $requested_by_user_id
 * @property int|null $decided_by_user_id
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Webpage $webpage
 * @property-read Website $website
 * @property-read User|null $decidedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoContentSuggestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoContentSuggestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeoContentSuggestion query()
 * @mixin \Eloquent
 */
class SeoContentSuggestion extends Model
{
    public const string FIELD_TITLE = 'title';

    public const string FIELD_DESCRIPTION = 'description';

    public const string REASON_REQUESTED = 'requested';

    public const string REASON_LOW_CTR = 'low_ctr';

    protected $guarded = [];

    protected $attributes = [
        'state' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'state'      => SeoContentSuggestionStateEnum::class,
            'decided_at' => 'datetime',
        ];
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
