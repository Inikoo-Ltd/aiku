<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Verb the AI editor picked for the Verba Home feed. Written once for everyone; who sees it follows the
 * membership of its conversation or the Slack channel's visibility, never anything stored here.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $staff_message_id
 * @property int|null $staff_conversation_id
 * @property int|null $slack_message_id
 * @property int|null $slack_channel_id
 * @property int|null $author_user_id
 * @property string $source quick (written seconds after posting, the editor may replace or remove it) or editor
 * @property string $category
 * @property int $importance
 * @property string $headline
 * @property string $blurb
 * @property \Illuminate\Support\Carbon $happened_at
 */
class StaffMessageStory extends Model
{
    public const string SOURCE_QUICK = 'quick';

    public const string SOURCE_EDITOR = 'editor';

    public const array CATEGORIES = ['celebration', 'product', 'people', 'announcement', 'ops', 'sales', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'importance'  => 'integer',
        ];
    }

    /**
     * @param  array{category: string, importance: int|string, headline: string, blurb: string}  $story
     */
    public static function storeFor(StaffMessage|SlackMessage $message, array $story, string $source): self
    {
        $isStaff = $message instanceof StaffMessage;

        return self::updateOrCreate(
            $isStaff ? ['staff_message_id' => $message->id] : ['slack_message_id' => $message->id],
            [
                'group_id'              => $isStaff ? $message->conversation->group_id : $message->channel->group_id,
                'staff_conversation_id' => $isStaff ? $message->staff_conversation_id : null,
                'slack_channel_id'      => $isStaff ? null : $message->slack_channel_id,
                'author_user_id'        => $message->user_id,
                'source'                => $source,
                'category'              => in_array($story['category'], self::CATEGORIES, true) ? $story['category'] : 'other',
                'importance'            => max(1, min(5, (int) $story['importance'])),
                'headline'              => mb_substr(trim($story['headline']), 0, 120),
                'blurb'                 => mb_substr(trim($story['blurb']), 0, 400),
                'happened_at'           => $isStaff ? $message->created_at : $message->sent_at,
            ]
        );
    }

    public function staffMessage(): BelongsTo
    {
        return $this->belongsTo(StaffMessage::class);
    }

    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class);
    }

    public function staffConversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class);
    }

    public function slackChannel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('group_id', $user->group_id)->where(function (Builder $query) use ($user) {
            $query->whereIn('staff_conversation_id', fn ($participants) => $participants->select('staff_conversation_id')
                ->from('staff_conversation_participants')
                ->where('user_id', $user->id)
                ->whereNull('left_at'))
                ->orWhereIn('slack_channel_id', SlackChannel::query()->visibleTo($user)->select('slack_channels.id'));
        });
    }
}
