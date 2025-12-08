<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Nov 2024 13:14:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use App\Enums\Comms\Outbox\OutboxTypeEnum;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

trait HasCommsIntervals
{
    use HasDateIntervalsStats;

    protected function getCommsIntervals($case, $table): Blueprint
    {
        $outboxType = Str::snake(str_replace('-', '_', $case->value));

        $fields = [
            $outboxType.'_dispatched_emails',
            $outboxType.'_opened_emails',
            $outboxType.'_clicked_emails',
            $outboxType.'_bounced_emails',
            $outboxType.'_subscribed',
            $outboxType.'_unsubscribed',

        ];

        if (in_array($case, [
            OutboxTypeEnum::CUSTOMER_NOTIFICATION,
            OutboxTypeEnum::USER_NOTIFICATION,
        ])) {
            unset($fields[$outboxType.'_subscribed']);
            unset($fields[$outboxType.'_unsubscribed']);
        }

        if ($case == OutboxTypeEnum::USER_NOTIFICATION) {
            unset($fields[$outboxType.'_opened_emails']);
            unset($fields[$outboxType.'_clicked_emails']);
        }

        return $this->unsignedIntegerDateIntervals($table, $fields);
    }
}
