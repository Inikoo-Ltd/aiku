<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Mar 2026 17:16:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Comms;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedDispatchedEmailsTables
{
    use AsAction;
    public string $commandSignature = 'repair:seed-dispatched-emails-tables';


    public function handle(Command $command): void
    {
        $command->info('Starting to seed dispatched emails pivot tables...');

        $processedCount = 0;
        $chunkSize      = 1000;

        DB::table('dispatched_emails')
            ->whereNotNull('parent_type')
            ->orderBy('id')
            ->chunk($chunkSize, function ($dispatchedEmails) use ($command, &$processedCount) {
                $emailOngoingRunInserts = [];
                $emailBulkRunInserts    = [];
                $mailshotInserts        = [];
                $idsToUpdate            = [];

                foreach ($dispatchedEmails as $dispatchedEmail) {
                    $idsToUpdate[] = $dispatchedEmail->id;

                    $insertData = [
                        'dispatched_email_id' => $dispatchedEmail->id,
                    ];

                    if ($dispatchedEmail->parent_type === 'EmailOngoingRun') {
                        $exists = DB::table('email_ongoing_run_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('email_ongoing_run_id', $dispatchedEmail->parent_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['email_ongoing_run_id'] = $dispatchedEmail->parent_id;
                            $emailOngoingRunInserts[]           = $insertData;
                        }
                    } elseif ($dispatchedEmail->parent_type === 'EmailBulkRun') {
                        $exists = DB::table('email_bulk_run_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('email_bulk_run_id', $dispatchedEmail->parent_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['email_bulk_run_id'] = $dispatchedEmail->parent_id;
                            $emailBulkRunInserts[]           = $insertData;
                        }
                    } elseif ($dispatchedEmail->parent_type === 'Mailshot') {
                        $exists = DB::table('mailshot_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('mailshot_id', $dispatchedEmail->parent_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['mailshot_id'] = $dispatchedEmail->parent_id;
                            $mailshotInserts[]         = $insertData;
                        }
                    }
                }

                if (!empty($emailOngoingRunInserts)) {
                    DB::table('email_ongoing_run_has_dispatched_emails')->insert($emailOngoingRunInserts);
                }

                if (!empty($emailBulkRunInserts)) {
                    DB::table('email_bulk_run_has_dispatched_emails')->insert($emailBulkRunInserts);
                }

                if (!empty($mailshotInserts)) {
                    DB::table('mailshot_has_dispatched_emails')->insert($mailshotInserts);
                }

                //                DB::table('dispatched_emails')
                //                    ->whereIn('id', $idsToUpdate)
                //                    ->update([
                //                        'parent_type' => null,
                //                        'parent_id'   => null,
                //                    ]);

                $processedCount += count($dispatchedEmails);
                $command->info("Processed $processedCount dispatched emails...");
            });

        $command->info("Completed! Total processed: $processedCount dispatched emails.");
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command);
    }
}
