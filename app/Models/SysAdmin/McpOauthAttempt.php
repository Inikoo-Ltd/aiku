<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 12:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\SysAdmin\McpOauthAttempt\McpOauthAttemptOutcomeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $group_id
 * @property int|null $user_id
 * @property string|null $client_id
 * @property string|null $client_name
 * @property McpOauthAttemptOutcomeEnum $outcome
 * @property int $status_code
 * @property string|null $error
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read \App\Models\SysAdmin\User|null $user
 */
class McpOauthAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'outcome' => McpOauthAttemptOutcomeEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
