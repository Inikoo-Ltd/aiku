<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person (or bot) of the Slack workspace, kept after they leave so their old messages still show a name.
 *
 * @property int $id
 * @property string $slack_id
 * @property string|null $name
 * @property string|null $real_name
 * @property string|null $display_name
 * @property string|null $email
 * @property string|null $avatar_url
 * @property bool $is_bot
 * @property bool $is_deleted
 * @property int|null $user_id
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $link_confirmed_at
 * @property int|null $link_confirmed_by_user_id
 */
class SlackUser extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'is_bot'     => 'boolean',
            'is_deleted' => 'boolean',
            'link_confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
