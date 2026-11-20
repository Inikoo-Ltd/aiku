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
 * An earlier wording of an edited Slack message, as it was before the edit.
 *
 * @property int $id
 * @property int $slack_message_id
 * @property string|null $text
 * @property array|null $data
 * @property \Illuminate\Support\Carbon $replaced_at
 */
class SlackMessageVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'        => 'array',
            'replaced_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }
}
