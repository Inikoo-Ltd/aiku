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

class SeedDispatchedEmailsRecipientsTables
{
    use AsAction;

    public string $commandSignature = 'repair:seed-dispatched-emails-recipients-tables';


    public function handle(Command $command): void
    {
        $command->info('Starting to seed dispatched emails recipients pivot tables...');

        $processedCount = 0;
        $chunkSize      = 1000;

        DB::table('dispatched_emails')
            ->whereNotNull('recipient_type')
            ->orderBy('id', 'desc')
            ->chunk($chunkSize, function ($dispatchedEmails) use ($command, &$processedCount) {
                $webUserInserts                    = [];
                $customerInserts                   = [];
                $prospectInserts                   = [];
                $externalSubscriberRecipientInserts = [];
                $testEmailRecipientInserts         = [];
                $chatEmailRecipientInserts         = [];
                $idsToUpdate                       = [];

                foreach ($dispatchedEmails as $dispatchedEmail) {
                    if (!$dispatchedEmail->recipient_id) {
                        continue;
                    }

                    $idsToUpdate[] = $dispatchedEmail->id;

                    $insertData = [
                        'dispatched_email_id' => $dispatchedEmail->id,
                    ];

                    if ($dispatchedEmail->recipient_type === 'WebUser') {
                        $exists = DB::table('web_user_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('web_user_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        $webUserExist = DB::table('web_users')->where('id', $dispatchedEmail->recipient_id)->exists();

                        if (!$exists && $webUserExist) {
                            $insertData['web_user_id'] = $dispatchedEmail->recipient_id;
                            $webUserInserts[]          = $insertData;
                        }
                    } elseif ($dispatchedEmail->recipient_type === 'Customer') {
                        $exists = DB::table('customer_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('customer_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['customer_id'] = $dispatchedEmail->recipient_id;
                            $customerInserts[]         = $insertData;
                        }
                    } elseif ($dispatchedEmail->recipient_type === 'Prospect') {
                        $exists = DB::table('prospect_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('prospect_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['prospect_id'] = $dispatchedEmail->recipient_id;
                            $prospectInserts[]         = $insertData;
                        }
                    } elseif ($dispatchedEmail->recipient_type === 'ExternalSubscriberEmailRecipient') {
                        $exists = DB::table('external_subscriber_email_recipient_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('external_subscriber_email_recipient_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['external_subscriber_email_recipient_id'] = $dispatchedEmail->recipient_id;
                            $externalSubscriberRecipientInserts[]                = $insertData;
                        }
                    } elseif ($dispatchedEmail->recipient_type === 'TestEmailRecipient') {
                        $exists = DB::table('test_email_recipient_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('test_email_recipient_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['test_email_recipient_id'] = $dispatchedEmail->recipient_id;
                            $testEmailRecipientInserts[]           = $insertData;
                        }
                    } elseif ($dispatchedEmail->recipient_type === 'ChatEmailRecipient') {
                        $exists = DB::table('chat_email_recipient_has_dispatched_emails')
                            ->where('dispatched_email_id', $dispatchedEmail->id)
                            ->where('chat_email_recipient_id', $dispatchedEmail->recipient_id)
                            ->exists();

                        if (!$exists) {
                            $insertData['chat_email_recipient_id'] = $dispatchedEmail->recipient_id;
                            $chatEmailRecipientInserts[]           = $insertData;
                        }
                    }
                }

                $changed = false;
                if (!empty($webUserInserts)) {
                    try {
                        DB::table('web_user_has_dispatched_emails')->insert($webUserInserts);
                        $changed = true;
                    }catch (\Exception $e) {

                        $command->error("Web user insert error: {$e->getMessage()}");
                    }
                }

                if (!empty($customerInserts)) {
                    DB::table('customer_has_dispatched_emails')->insert($customerInserts);
                    $changed = true;
                }

                if (!empty($prospectInserts)) {
                    DB::table('prospect_has_dispatched_emails')->insert($prospectInserts);
                    $changed = true;
                }

                if (!empty($externalSubscriberRecipientInserts)) {
                    DB::table('external_subscriber_email_recipient_has_dispatched_emails')->insert($externalSubscriberRecipientInserts);
                    $changed = true;
                }

                if (!empty($testEmailRecipientInserts)) {
                    DB::table('test_email_recipient_has_dispatched_emails')->insert($testEmailRecipientInserts);
                    $changed = true;
                }

                if (!empty($chatEmailRecipientInserts)) {
                    DB::table('chat_email_recipient_has_dispatched_emails')->insert($chatEmailRecipientInserts);
                    $changed = true;
                }

                if ($changed) {
                    DB::table('dispatched_emails')
                        ->whereIn('id', $idsToUpdate)
                        ->update([
                            'recipient_type' => null,
                            'recipient_id'   => null,
                        ]);
                }

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
