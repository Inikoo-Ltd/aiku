<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 */
class SlackMessage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'      => 'array',
            'sent_at'   => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SlackChannel::class, 'slack_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
