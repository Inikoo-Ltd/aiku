<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A public Slack channel whose messages are kept in aiku.
 *
 * @property int $id
 * @property int $group_id
 * @property string $slack_id
 * @property string|null $name
 * @property bool $is_archived
 * @property bool $is_member
 * @property array|null $data
 * @property string|null $history_backfilled_to
 * @property \Illuminate\Support\Carbon|null $history_synced_at
 */
class SlackChannel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_archived'       => 'boolean',
            'is_member'         => 'boolean',
            'data'              => 'array',
            'history_synced_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SlackMessage::class);
    }
}
