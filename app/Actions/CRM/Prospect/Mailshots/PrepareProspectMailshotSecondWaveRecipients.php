<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 09:40:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Actions\Comms\Mailshot\UpdateMailshotRecipientsStoredAt;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Models\Comms\Mailshot;
use App\Models\CRM\Prospect;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrepareProspectMailshotSecondWaveRecipients
{
    use AsAction;

    public string $jobQueue = 'ses';
    protected int $countRecipients = 0;

    public function tags(): array
    {
        return ['send_prospect_mailshot_second_wave'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $chunkSize      = 50;
        $parentMailshot = $mailshot->parentMailshot;

        if (!$parentMailshot) {
            Log::warning('Mailshot does not have parent mailshot, skipping second wave processing', [
                'mailshot_id' => $mailshot->id,
            ]);

            return;
        }

        $baseQuery = DB::table('prospects');
        $baseQuery->join('mailshot_recipients', 'prospects.id', '=', 'mailshot_recipients.recipient_id');
        $baseQuery->join('dispatched_emails', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id');
        $baseQuery->where('mailshot_recipients.mailshot_id', $parentMailshot->id);
        $baseQuery->where('mailshot_recipients.recipient_type', class_basename(Prospect::class));

        $baseQuery->whereIn('dispatched_emails.state', [DispatchedEmailStateEnum::SENT->value, DispatchedEmailStateEnum::DELIVERED->value]);
        $baseQuery->whereNotNull('dispatched_emails.sent_at');

        $baseQuery->where('prospects.shop_id', $mailshot->shop_id)
            ->whereNull('prospects.customer_id')
            ->where('prospects.can_contact_by_email', true)
            ->where('prospects.is_valid_email', true)
            ->whereNotNull('prospects.email');
        $baseQuery->whereNull('prospects.deleted_at');

        $baseQuery->select('prospects.id', 'prospects.email');
        $baseQuery->groupBy('prospects.id');
        $baseQuery->orderBy('prospects.id');

        $mailshotId = $mailshot->id;

        $baseQuery->chunk($chunkSize, function ($prospects) use ($mailshotId) {
            $prospectIds    = [];
            $numValidEmails = 0;
            foreach ($prospects as $prospect) {
                if (filter_var($prospect->email, FILTER_VALIDATE_EMAIL)) {
                    $prospectIds[] = $prospect->id;
                    $numValidEmails++;
                }
            }

            if (!empty($prospectIds)) {
                ProcessSendProspectMailshot::dispatch($mailshotId, $prospectIds);
                $this->countRecipients += $numValidEmails;
            }
        });

        UpdateMailshot::run(
            $mailshot,
            [
                'recipients_prepared_at' => now(),
                'recipients_count'       => $this->countRecipients,
            ]
        );

        UpdateMailshotRecipientsStoredAt::run($mailshot);

        GroupHydrateMailshots::dispatch($mailshot->group);
        OrganisationHydrateMailshots::dispatch($mailshot->organisation);
        OutboxHydrateMailshots::dispatch($mailshot->outbox);
        ShopHydrateMailshots::dispatch($mailshot->shop);
    }

    public string $commandSignature = 'prospect-mailshot-second-wave:prepare {mailshot}';

    public function asCommand(Command $command): int
    {
        try {
            $mailshot = Mailshot::where('slug', $command->argument('mailshot'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }

        $this->handle($mailshot);

        return 0;
    }
}
