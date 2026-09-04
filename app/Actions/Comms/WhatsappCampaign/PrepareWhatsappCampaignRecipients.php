<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Hands the recipients the picker already stored to the jobs that send them.
 *
 * The rows exist before this runs, written by StoreWhatsappCampaignRecipients with a null
 * whatsapp_delivery_channel_id. That null is what is left to do: this walks the unclaimed
 * rows and batches them, and ProcessSendWhatsappCampaign stamps each batch with the channel
 * it created. A row that already carries a channel is somebody else's, so re-running this
 * after a partial send picks up only what was missed.
 *
 * The name and customer behind each number were resolved when the audience was chosen and
 * are on the row, so nothing is looked up again here.
 */
class PrepareWhatsappCampaignRecipients
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function tags(): array
    {
        return ['send_whatsapp_campaign'];
    }

    private const CHUNK_SIZE = 50;

    public function handle(WhatsappCampaign $campaign): void
    {
        /* chunkById rather than chunk: the dispatched job claims the rows it was given, so
           on a sync queue the where clause stops matching them mid walk and an offset paged
           read would skip a batch for every one it handed out. */
        $campaign->recipients()
            ->whereNull('whatsapp_delivery_channel_id')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $recipients) use ($campaign) {
                $rows = $this->buildRows($recipients);

                if ($rows) {
                    ProcessSendWhatsappCampaign::dispatch($campaign->id, $rows);
                }
            });
    }

    /**
     * Ids rather than whole rows, so the job payload stays small and the job reads the row
     * as it is when it runs rather than as it was when it was queued.
     *
     * The phone is re-checked here rather than trusted: a selection saved before the
     * audience started filtering unsendable numbers still holds them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(Collection $recipients): array
    {
        return $recipients
            ->filter(fn ($recipient) => GetWhatsappRecipientsQuery::isSendablePhone($recipient->phone))
            ->map(fn ($recipient) => [
                'recipient_id' => $recipient->id,
                'phone'        => $recipient->phone,
                'name'         => $recipient->recipient_name,
                'customer_id'  => $recipient->recipient_type == 'Customer' ? $recipient->recipient_id : null,
            ])
            ->values()
            ->all();
    }

    public string $commandSignature = 'whatsapp-campaign:prepare-recipients {campaign}';

    public function asCommand(Command $command): int
    {
        $campaign = WhatsappCampaign::where('slug', $command->argument('campaign'))->first();

        if (!$campaign) {
            $command->error('Campaign not found');

            return 1;
        }

        $this->handle($campaign);

        return 0;
    }
}
