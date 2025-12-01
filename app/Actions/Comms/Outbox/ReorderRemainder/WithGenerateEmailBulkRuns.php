<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Sat, 27 Sept 2025 23:50:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\ReorderRemainder;

use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\Comms\EmailBulkRun;

trait WithGenerateEmailBulkRuns
{
    /**
     * Trait to generate EmailBulkRuns related to ReorderRemainder
     */
    protected function generateEmailBulkRuns(
        Customer $customer,
        OutboxCodeEnum $code,
        ?string $date = null,
    ): EmailBulkRun {
        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', $code->value)->first();


        // find email bulk email for today is exit or not
        $emailBulkRun = $outbox->emailBulkRuns()
            ->whereDate('created_at', $date)
            ->first();

        if ($emailBulkRun) {
            return $emailBulkRun;
        }

        $emailBulkRun = StoreEmailBulkRun::run($outbox->emailOngoingRun, [
            'scheduled_at' => $date,
            'subject'      => now()->format('Y.m.d'),
            'state'        => EmailBulkRunStateEnum::SENDING,
        ]);

        return $emailBulkRun;
    }
}
