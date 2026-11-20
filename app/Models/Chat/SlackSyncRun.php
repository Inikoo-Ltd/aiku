<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;

/**
 * One run of slack:sync-public-channels; error is set when it stopped on a Slack error.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int|null $channels
 * @property int|null $messages_read
 * @property int|null $messages_added
 * @property string|null $error
 */
class SlackSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'  => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
