<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 5 Feb 2026 09:40:43 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrepareMailshotSecondWaveRecipients
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function tags(): array
    {
        return ['send_mailshot_second_wave'];
    }

    public function handle(Mailshot $mailshot): void
    {

        $chunkSize = 100;
        $parentMailshot = $mailshot->parentMailshot;

        if (!$parentMailshot) {
            Log::warning('Mailshot does not have parent mailshot, skipping second wave processing', [
                'mailshot_id' => $mailshot->id,
            ]);
            return;
        }

        $outboxId = $parentMailshot->outbox->id;

        $baseQuery = DB::table('customers');
        $baseQuery->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
        $baseQuery->join('mailshot_recipients', 'customers.id', '=', 'mailshot_recipients.customer_id');
        $baseQuery->join('dispatched_emails', 'mailshot_recipients.dispatched_email_id', '=', 'dispatched_emails.id');
        $baseQuery->where('mailshot_recipients.mailshot_id', $parentMailshot->id);

        $baseQuery->where('dispatched_emails.state', DispatchedEmailStateEnum::SENT->value);
        $baseQuery->whereNotNull('dispatched_emails.sent_at');

        $baseQuery->where('customers.shop_id', $mailshot->shop_id);
        $baseQuery->whereNotNull('customers.email');

        switch ($mailshot->type) {
            case MailshotTypeEnum::NEWSLETTER:
                $baseQuery->where('customer_comms.is_subscribed_to_newsletter', true);
                break;
            case MailshotTypeEnum::MARKETING:
                $baseQuery->where('customer_comms.is_subscribed_to_marketing', true);
                break;
            default:
                // Return invalid query for unsupported types
                $baseQuery->whereRaw('1 = 0');
                break;
        }
        $baseQuery->groupBy('customers.id');

        $cloneQuery = $baseQuery->clone();
        $totalCustomers = $cloneQuery->count('customers.id');


        $mailshotId = $mailshot->id;
        // NOTE: for debug the SQl query
        // \Log::info($queryBuilder->toRawSql());

        // Process recipients in chunks of 250
        $baseQuery->select('customers.id')->chunk($chunkSize, function ($customers) use ($mailshotId, $outboxId, $totalCustomers) {
            $customerIds = $customers->pluck('id');
            ProcessSendMailshot::dispatch($mailshotId, $customerIds, $outboxId, $totalCustomers);
        });

        GroupHydrateMailshots::dispatch($mailshot->group);
        OrganisationHydrateMailshots::dispatch($mailshot->organisation);
        OutboxHydrateMailshots::dispatch($mailshot->outbox);
        ShopHydrateMailshots::dispatch($mailshot->shop);
    }

    public string $commandSignature = 'send:mailshot-second-wave {mailshot}';

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
