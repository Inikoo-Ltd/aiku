<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;

/**
 * One reading of a claude.ai account's weekly usage limit, as Claude Code saw it.
 *
 * @property int $id
 * @property int $group_id
 * @property string $account
 * @property string $percent_used
 * @property \Illuminate\Support\Carbon $resets_at
 * @property \Illuminate\Support\Carbon $sampled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ChatAiAccountUsageSample extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'percent_used' => 'decimal:2',
            'resets_at'    => 'datetime',
            'sampled_at'   => 'datetime',
        ];
    }
}
