<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:30:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\UserStats
 *
 * @property int $id
 * @property int $user_id
 * @property int $number_logins
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_active_at
 * @property int $number_failed_logins
 * @property string|null $last_failed_login_ip
 * @property string|null $last_failed_login_at
 * @property int $number_audits
 * @property int $number_audits_event_created
 * @property int $number_audits_event_updated
 * @property int $number_audits_event_deleted
 * @property int $number_audits_event_restored
 * @property int $number_audits_event_customer_note
 * @property int $number_audits_event_other
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_current_api_tokens
 * @property int $number_expired_api_tokens
 * @property-read \App\Models\SysAdmin\User $user
 * @method static Builder<static>|UserStats newModelQuery()
 * @method static Builder<static>|UserStats newQuery()
 * @method static Builder<static>|UserStats query()
 * @mixin Eloquent
 */
class UserStats extends Model
{
    protected $table = 'user_stats';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
