<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 19:53:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Enums\Miscellaneous\GenderEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasCRMStats
{
    public function customerStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_customers')->default(0);

        foreach (CustomerStateEnum::cases() as $customerState) {
            $table->unsignedInteger("number_customers_state_{$customerState->snake()}")->default(0);
        }
        foreach (CustomerStatusEnum::cases() as $customerStatus) {
            $table->unsignedInteger("number_customers_status_{$customerStatus->snake()}")->default(0);
        }
        foreach (CustomerTradeStateEnum::cases() as $tradeState) {
            $table->unsignedInteger('number_customers_trade_state_'.$tradeState->snake())->default(0);
        }

        return $table;
    }

    public function getWebUsersStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_web_users')->default(0);
        $table->unsignedInteger('number_current_web_users')->default(0)->comment('Number of web users with state = true');

        foreach (WebUserTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_web_users_type_'.$case->snake())->default(0);
        }

        foreach (WebUserAuthTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_web_users_auth_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function getPollsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_polls')->default(0);
        $table->unsignedSmallInteger('number_polls_in_registration')->default(0);
        $table->unsignedSmallInteger('number_polls_required_in_registration')->default(0);
        $table->unsignedSmallInteger('number_polls_in_iris')->default(0);
        $table->unsignedSmallInteger('number_polls_required_in_iris')->default(0);

        foreach (PollTypeEnum::cases() as $case) {
            $table->unsignedInteger('number_polls_type_'.$case->snake())->default(0);
            $table->unsignedSmallInteger('number_polls_in_registration_type_'.$case->snake())->default(0);
            $table->unsignedSmallInteger('number_polls_required_in_registration_type_'.$case->snake())->default(0);
            $table->unsignedSmallInteger('number_polls_in_iris_type_'.$case->snake())->default(0);
            $table->unsignedSmallInteger('number_polls_required_in_iris_type_'.$case->snake())->default(0);
        }

        return $table;
    }

    public function prospectsStats(Blueprint $table): Blueprint
    {
        $table->unsignedInteger('number_prospects')->default(0);

        foreach (ProspectStateEnum::cases() as $prospectState) {
            $table->unsignedInteger("number_prospects_state_{$prospectState->snake()}")->default(0);
        }

        foreach (GenderEnum::cases() as $case) {
            $table->unsignedSmallInteger('number_prospects_gender_'.$case->snake())->default(0);
        }

        foreach (ProspectContactedStateEnum::cases() as $case) {
            $table->unsignedInteger("number_prospects_contacted_state_{$case->snake()}")->default(0);
        }

        foreach (ProspectFailStatusEnum::cases() as $case) {
            $table->unsignedInteger("number_prospects_fail_status_{$case->snake()}")->default(0);
        }

        foreach (ProspectSuccessStatusEnum::cases() as $case) {
            $table->unsignedInteger("number_prospects_success_status_{$case->snake()}")->default(0);
        }

        $table->unsignedInteger('number_prospects_dont_contact_me')->default(0);

        return $table;
    }

    public function crmQueriesStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_prospect_queries')->default(0);
        $table->unsignedSmallInteger('number_customer_queries')->default(0);

        $table->unsignedSmallInteger('number_prospect_static_queries')->default(0)->comment('is_static=true');
        $table->unsignedSmallInteger('number_prospect_dynamic_queries')->default(0)->comment('is_static=false');

        $table->unsignedSmallInteger('number_customer_static_queries')->default(0)->comment('is_static=true');
        $table->unsignedSmallInteger('number_customer_dynamic_queries')->default(0)->comment('is_static=false');

        return $table;
    }
}
