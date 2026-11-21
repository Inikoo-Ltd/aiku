<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Actions\Chat\Staff\Slack\RefreshSlackMessageSearch;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A message of a public Slack channel as Slack sent it; ts is Slack's own id of the message within its channel.
 *
 * @property int $id
 * @property int $slack_channel_id
 * @property string $ts
 * @property string|null $thread_ts
 * @property string|null $slack_user_id
 * @property int|null $user_id
 * @property string|null $subtype
 * @property string|null $text
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $pinned_at
 * @property string|null $pinned_by_slack_user_id
 */
class SlackMessage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saved(function (SlackMessage $message) {
            if ($message->wasRecentlyCreated || $message->wasChanged('text')) {
                rescue(fn () => RefreshSlackMessageSearch::make()->handle(collect([$message])));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'data'      => 'array',
            'sent_at'   => 'datetime',
            'edited_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SlackMessageVersion::class)->orderBy('id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class, 'slack_channel_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(SlackMessageReaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slackUser(): BelongsTo
    {
        return $this->belongsTo(SlackUser::class, 'slack_user_id', 'slack_id');
    }
}
