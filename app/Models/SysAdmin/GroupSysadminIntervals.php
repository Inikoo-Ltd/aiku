<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Dec 2024 14:57:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $user_requests_all
 * @property int $user_requests_1y
 * @property int $user_requests_1q
 * @property int $user_requests_1m
 * @property int $user_requests_1w
 * @property int $user_requests_3d
 * @property int $user_requests_1d
 * @property int $user_requests_ytd
 * @property int $user_requests_qtd
 * @property int $user_requests_mtd
 * @property int $user_requests_wtd
 * @property int $user_requests_tdy
 * @property int $user_requests_lm
 * @property int $user_requests_lw
 * @property int $user_requests_ld
 * @property int $user_requests_1y_ly
 * @property int $user_requests_1q_ly
 * @property int $user_requests_1m_ly
 * @property int $user_requests_1w_ly
 * @property int $user_requests_3d_ly
 * @property int $user_requests_1d_ly
 * @property int $user_requests_ytd_ly
 * @property int $user_requests_qtd_ly
 * @property int $user_requests_mtd_ly
 * @property int $user_requests_wtd_ly
 * @property int $user_requests_tdy_ly
 * @property int $user_requests_lm_ly
 * @property int $user_requests_lw_ly
 * @property int $user_requests_ld_ly
 * @property int $user_requests_py1
 * @property int $user_requests_py2
 * @property int $user_requests_py3
 * @property int $user_requests_py4
 * @property int $user_requests_py5
 * @property int $user_requests_pq1
 * @property int $user_requests_pq2
 * @property int $user_requests_pq3
 * @property int $user_requests_pq4
 * @property int $user_requests_pq5
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysadminIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysadminIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupSysadminIntervals query()
 * @mixin \Eloquent
 */
class GroupSysadminIntervals extends Model
{
    protected $table = 'group_sysadmin_intervals';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
