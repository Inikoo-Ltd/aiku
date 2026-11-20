<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's emoji on a kept Slack message; name is the emoji name without colons.
 *
 * @property int $id
 * @property int $slack_message_id
 * @property string $slack_user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $reacted_at
 */
class SlackMessageReaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'reacted_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }
}
