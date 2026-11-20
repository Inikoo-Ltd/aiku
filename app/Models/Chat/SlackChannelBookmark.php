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
 * A bookmark at the top of a public Slack channel; position is Slack's rank and sorts as text.
 *
 * @property int $id
 * @property int $slack_channel_id
 * @property string $slack_id
 * @property string|null $title
 * @property string|null $link
 * @property string|null $emoji
 * @property string|null $position
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $removed_in_slack_at
 */
class SlackChannelBookmark extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'                => 'array',
            'removed_in_slack_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class, 'slack_channel_id');
    }
}
