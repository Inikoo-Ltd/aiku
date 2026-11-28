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
 * "Remind me about this message" (INI-471): one bell notification at remind_at with a link to a Slack or Verba message,
 * for the person who asked only. Nothing is sent to Slack.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $slack_message_id
 * @property int|null $staff_message_id
 * @property Carbon $remind_at
 * @property Carbon|null $rung_at
 * @property-read User $user
 * @property-read SlackMessage|null $slackMessage
 * @property-read StaffMessage|null $staffMessage
 */
class StaffMessageReminder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'rung_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class)->withTrashed();
    }

    public function staffMessage(): BelongsTo
    {
        return $this->belongsTo(StaffMessage::class)->withTrashed();
    }
}
