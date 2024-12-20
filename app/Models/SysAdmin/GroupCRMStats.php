<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Jan 2024 12:45:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SysAdmin\GroupCRMStats
 *
 * @property int $id
 * @property int $group_id
 * @property int $number_customers
 * @property int $number_customers_state_in_process
 * @property int $number_customers_state_registered
 * @property int $number_customers_state_active
 * @property int $number_customers_state_losing
 * @property int $number_customers_state_lost
 * @property int $number_customers_trade_state_none
 * @property int $number_customers_trade_state_one
 * @property int $number_customers_trade_state_many
 * @property int $number_prospects
 * @property int $number_prospects_state_no_contacted
 * @property int $number_prospects_state_contacted
 * @property int $number_prospects_state_fail
 * @property int $number_prospects_state_success
 * @property int $number_prospects_gender_male
 * @property int $number_prospects_gender_female
 * @property int $number_prospects_gender_other
 * @property int $number_prospects_contacted_state_no_applicable
 * @property int $number_prospects_contacted_state_soft_bounced
 * @property int $number_prospects_contacted_state_never_open
 * @property int $number_prospects_contacted_state_open
 * @property int $number_prospects_contacted_state_clicked
 * @property int $number_prospects_fail_status_no_applicable
 * @property int $number_prospects_fail_status_not_interested
 * @property int $number_prospects_fail_status_unsubscribed
 * @property int $number_prospects_fail_status_hard_bounced
 * @property int $number_prospects_fail_status_invalid
 * @property int $number_prospects_success_status_no_applicable
 * @property int $number_prospects_success_status_registered
 * @property int $number_prospects_success_status_invoiced
 * @property int $number_prospects_dont_contact_me
 * @property int $number_prospect_queries
 * @property int $number_customer_queries
 * @property int $number_polls
 * @property int $number_polls_in_registration
 * @property int $number_polls_required_in_registration
 * @property int $number_polls_in_iris
 * @property int $number_polls_required_in_iris
 * @property int $number_polls_type_open_question
 * @property int $number_polls_in_registration_type_open_question
 * @property int $number_polls_required_in_registration_type_open_question
 * @property int $number_polls_in_iris_type_open_question
 * @property int $number_polls_required_in_iris_type_open_question
 * @property int $number_polls_type_option
 * @property int $number_polls_in_registration_type_option
 * @property int $number_polls_required_in_registration_type_option
 * @property int $number_polls_in_iris_type_option
 * @property int $number_polls_required_in_iris_type_option
 * @property int $number_web_users
 * @property int $number_current_web_users Number of web users with state = true
 * @property int $number_web_users_type_web
 * @property int $number_web_users_type_api
 * @property int $number_web_users_auth_type_default
 * @property int $number_web_users_auth_type_aurora
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCRMStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCRMStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupCRMStats query()
 * @mixin \Eloquent
 */
class GroupCRMStats extends Model
{
    protected $table = 'group_crm_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
