<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:00:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $poll_id
 * @property int $poll_option_id
 * @property int $number_customers
 * @property int $number_customers_state_in_process
 * @property int $number_customers_state_registered
 * @property int $number_customers_state_active
 * @property int $number_customers_state_losing
 * @property int $number_customers_state_lost
 * @property int $number_customers_status_pending_approval
 * @property int $number_customers_status_approved
 * @property int $number_customers_status_rejected
 * @property int $number_customers_status_banned
 * @property int $number_customers_trade_state_none
 * @property int $number_customers_trade_state_one
 * @property int $number_customers_trade_state_many
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $number_customers_status_pre_registration
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOptionStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOptionStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PollOptionStat query()
 * @mixin \Eloquent
 */
class PollOptionStat extends Model
{
    protected $casts = [
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

}
