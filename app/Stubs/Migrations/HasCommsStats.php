<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Apr 2023 10:21:27 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Schema\Blueprint;

trait HasCommsStats
{
    public function postRoomsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_post_rooms')->default(0);
        return $table;
    }

    public function orgPostRoomsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_org_post_rooms')->default(0);
        return $table;
    }

    public function outboxesStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_outboxes')->default(0);
        foreach (OutboxCodeEnum::cases() as $outboxTypeEnum) {
            $table->unsignedInteger('number_outboxes_type_'.$outboxTypeEnum->snake())->default(0);
        }
        foreach (OutboxStateEnum::cases() as $outboxStateEnum) {
            $table->unsignedInteger('number_outboxes_state_'.$outboxStateEnum->snake())->default(0);
        }

        $table->unsignedInteger('number_outbox_subscribers')->default(0);

        return $table;
    }

    public function mailshotsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_mailshots')->default(0);

        foreach (MailshotStateEnum::cases() as $state) {
            $table->unsignedInteger('number_post_room_state_'.$state->snake())->default(0);
        }

        return $table;
    }

    public function dispatchedEmailStats(Blueprint $table): Blueprint
    {
        $table->unsignedBigInteger('number_dispatched_emails')->default(0);

        foreach (DispatchedEmailStateEnum::cases() as $state) {
            $table->unsignedBigInteger("number_dispatched_emails_state_{$state->snake()}")->default(0);
        }
        $table->unsignedInteger('number_provoked_unsubscribe')->default(0);

        return $table;
    }
}
