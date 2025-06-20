<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:13:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupSysAdminStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_users
 * @property int $number_users_status_active
 * @property int $number_users_status_inactive
 * @property int $number_guests
 * @property int $number_guests_status_active
 * @property int $number_guests_status_inactive
 * @property int $number_user_requests
 * @property int $number_audits
 * @property int $number_audits_event_created
 * @property int $number_audits_event_updated
 * @property int $number_audits_event_deleted
 * @property int $number_audits_event_restored
 * @property int $number_audits_event_customer_note
 * @property int $number_audits_event_migrated
 * @property int $number_audits_event_other
 * @property int $number_audits_user_type_system
 * @property int $number_audits_user_type_user
 * @property int $number_audits_user_type_web_user
 * @property int $number_audits_user_type_other
 * @property int $number_audits_user_type_system_event_created
 * @property int $number_audits_user_type_system_event_updated
 * @property int $number_audits_user_type_system_event_deleted
 * @property int $number_audits_user_type_system_event_restored
 * @property int $number_audits_user_type_system_event_customer_note
 * @property int $number_audits_user_type_system_event_migrated
 * @property int $number_audits_user_type_system_event_other
 * @property int $number_audits_user_type_user_event_created
 * @property int $number_audits_user_type_user_event_updated
 * @property int $number_audits_user_type_user_event_deleted
 * @property int $number_audits_user_type_user_event_restored
 * @property int $number_audits_user_type_user_event_customer_note
 * @property int $number_audits_user_type_user_event_other
 * @property int $number_audits_user_type_web_user_event_created
 * @property int $number_audits_user_type_web_user_event_updated
 * @property int $number_audits_user_type_web_user_event_deleted
 * @property int $number_audits_user_type_web_user_event_restored
 * @property int $number_audits_user_type_web_user_event_customer_note
 * @property int $number_audits_user_type_web_user_event_other
 * @property int $number_audits_user_type_other_event_created
 * @property int $number_audits_user_type_other_event_updated
 * @property int $number_audits_user_type_other_event_deleted
 * @property int $number_audits_user_type_other_event_restored
 * @property int $number_audits_user_type_other_event_customer_note
 * @property int $number_audits_user_type_other_event_other
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_current_api_tokens
 * @property int $number_expired_api_tokens
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysAdminStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysAdminStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysAdminStats query()
 * @mixin \Eloquent
 */
class GroupSysAdminStats extends Model
{
    protected $table = 'group_sysadmin_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
