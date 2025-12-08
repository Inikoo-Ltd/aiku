<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jul 2023 12:33:47 Malaysia Time, plane Bali -> KL
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Enums\Helpers\Audit\AuditUserTypeEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasSysAdminStats
{
    public function guestsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_guests')->default(0);
        $table->unsignedSmallInteger('number_guests_status_active')->default(0);
        $table->unsignedSmallInteger('number_guests_status_inactive')->default(0);

        return $table;
    }

    public function userStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_users')->default(0);
        $table->unsignedSmallInteger('number_users_status_active')->default(0);
        $table->unsignedSmallInteger('number_users_status_inactive')->default(0);

        return $table;
    }

    public function userRequestsStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_user_requests')->default(0);

        return $table;
    }

    public function auditFields(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_audits')->default(0);

        foreach (AuditEventEnum::cases() as $case) {
            $table->unsignedBigInteger("number_audits_event_{$case->snake()}")->default(0);
        }

        foreach (AuditUserTypeEnum::cases() as $case) {
            $table->unsignedBigInteger("number_audits_user_type_{$case->snake()}")->default(0);
        }

        foreach (AuditUserTypeEnum::cases() as $case) {
            foreach (AuditEventEnum::cases() as $case2) {
                if ($case2 == AuditEventEnum::MIGRATED and $case != AuditUserTypeEnum::SYSTEM) {
                    continue;
                }
                $table->unsignedBigInteger("number_audits_user_type_{$case->snake()}_event_{$case2->snake()}")->default(0);
            }
        }

        return $table;
    }

    public function auditFieldsForNonSystem(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_audits')->default(0);

        foreach (AuditEventEnum::cases() as $case) {
            if ($case == AuditEventEnum::MIGRATED) {
                continue;
            }

            $table->unsignedBigInteger("number_audits_event_{$case->snake()}")->default(0);
        }

        return $table;
    }
}
