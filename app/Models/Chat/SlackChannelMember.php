<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Slack person (the aiku bot included) who is in a kept public Slack channel.
 *
 * @property int $id
 * @property int $slack_channel_id
 * @property string $slack_user_id
 * @property \Illuminate\Support\Carbon|null $joined_at
 */
class SlackChannelMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class, 'slack_channel_id');
    }

    public function slackUser(): BelongsTo
    {
        return $this->belongsTo(SlackUser::class, 'slack_user_id', 'slack_id');
    }
}
