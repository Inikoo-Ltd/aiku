<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Sat, 27 Sept 2025 23:50:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Models\Comms\Outbox;
use App\Models\Comms\EmailBulkRun;

trait WithGenerateEmailBulkRuns
{
    protected function upsertEmailBulkRuns(
        Outbox $outbox,
        ?string $date = null,
    ): EmailBulkRun {
        $date = $date ?? now()->toDateString();

        // find email bulk email for today
        $emailBulkRun = $outbox->emailBulkRuns()
            ->whereDate('created_at', $date)
            ->first();

        if ($emailBulkRun) {
            // Count existing runs for today
            $existingRunsCount = $outbox->emailBulkRuns()
                ->whereDate('created_at', $date)
                ->count();

            // Create new run with suffix
            $subject = now()->format('Y.m.d') . '_' . ($existingRunsCount);

            return StoreEmailBulkRun::run($outbox->emailOngoingRun, [
                'scheduled_at' => now(),
                'subject'      => $subject,
                'state'        => EmailBulkRunStateEnum::SENDING,
            ]);
        }

        return StoreEmailBulkRun::run($outbox->emailOngoingRun, [
            'scheduled_at' => now(),
            'subject'      => now()->format('Y.m.d'),
            'state'        => EmailBulkRunStateEnum::SENDING,
        ]);
    }
}
