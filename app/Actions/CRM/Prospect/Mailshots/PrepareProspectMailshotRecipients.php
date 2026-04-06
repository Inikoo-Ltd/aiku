<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 27 Feb 2025 15:28:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots;

use App\Actions\Comms\Mailshot\DeleteMailshotSecondWave;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Actions\Comms\Mailshot\UpdateMailshotRecipientsStoredAt;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Models\Comms\Mailshot;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PrepareProspectMailshotRecipients
{
    use AsAction;

    public string $jobQueue = 'default-long';

    protected int $countRecipients = 0;

    public function tags(): array
    {
        return ['send_prospect_mailshot'];
    }

    public function handle(Mailshot $mailshot): void
    {
        $chunkSize = 50;
        // NOTE: Ensure no second wave exists when the parent mailshot has second wave disabled
        if ($mailshot->secondWave()->exists() && !$mailshot->is_second_wave_enabled) {
            DeleteMailshotSecondWave::run($mailshot->secondWave);
        }

        $queryBuilder = DB::table('prospects')
            ->select('id', 'email')
            ->where('shop_id', $mailshot->shop_id)
            ->where('state', ProspectStateEnum::NO_CONTACTED->value)
            ->whereNull('customer_id')
            ->where('can_contact_by_email', true)
            ->where('dont_contact_me', false)
            ->where('is_valid_email', true)
            ->whereNotNull('email')
            ->whereNull('deleted_at')
            ->orderBy('id', 'asc');

        $mailshotId = $mailshot->id;

        // Process recipients in chunks of 250
        $queryBuilder->chunk($chunkSize, function ($recipients) use ($mailshotId) {
            $prospectIds = [];
            $numValidEmails = 0;
            foreach ($recipients as $recipient) {
                if (filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                    $prospectIds[] = $recipient->id;
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
    }

    public string $commandSignature = 'mailshot:send-prospects {mailshot}';

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
