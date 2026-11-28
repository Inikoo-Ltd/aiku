<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Verba message written now and sent later (INI-471): aiku keeps it and posts it at send_at through the same send
 * action as the box, as the person, into a Slack channel or DM (slack_channel_id) or a Verba conversation
 * (staff_conversation_id). A row is claimed (pending → sending) before it is posted, so it goes out once.
 *
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property int|null $slack_channel_id
 * @property int|null $staff_conversation_id
 * @property string|null $thread_ts
 * @property int|null $parent_id
 * @property string $text
 * @property bool $also_to_channel
 * @property bool $notify_everyone
 * @property Carbon $send_at
 * @property string $state
 * @property string|null $failure_reason
 * @property int|null $slack_message_id
 * @property int|null $staff_message_id
 * @property Carbon|null $claimed_at
 * @property Carbon|null $sent_at
 * @property-read User $user
 * @property-read SlackChannel|null $slackChannel
 * @property-read StaffConversation|null $staffConversation
 * @property-read SlackMessage|null $slackMessage
 * @property-read StaffMessage|null $staffMessage
 */
class StaffMessageSchedule extends Model
{
    public const string PENDING = 'pending';
    public const string SENDING = 'sending';
    public const string SENT = 'sent';
    public const string FAILED = 'failed';
    public const string CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'also_to_channel' => 'boolean',
            'notify_everyone' => 'boolean',
            'send_at'         => 'datetime',
            'claimed_at'      => 'datetime',
            'sent_at'         => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slackChannel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class);
    }

    public function staffConversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class);
    }

    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class);
    }

    public function staffMessage(): BelongsTo
    {
        return $this->belongsTo(StaffMessage::class);
    }
}
