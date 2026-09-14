<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Comms\WhatsappCampaign;
use App\Services\QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class RunWhatsappCampaignScheduled
{
    use AsAction;

    public string $jobQueue = 'long-send-emails';
    public string $commandSignature = 'run-whatsapp-campaign-scheduled';

    public function handle(): void
    {
        $currentDateTime = Carbon::now()->utc();

        $campaignQuery = QueryBuilder::for(WhatsappCampaign::class);
        $campaignQuery->where('state', WhatsappCampaignStateEnum::SCHEDULED);

        $campaignQuery->whereNull('deleted_at');
        $campaignQuery->whereNull('cancelled_at');
        $campaignQuery->whereNull('stopped_at');
        $campaignQuery->whereNull('sent_at');
        $campaignQuery->whereNull('start_sending_at');

        // the column is timestamptz, so both sides are normalised to UTC before comparing
        $campaignQuery->whereRaw("scheduled_at AT TIME ZONE 'UTC' <= ?", [$currentDateTime]);

        foreach ($campaignQuery->cursor() as $campaign) {
            /* A fill that has not landed yet is the one send time condition that fixes
               itself, so the campaign is left scheduled for the next run to pick up rather
               than ended for being early. */
            if ($campaign->isFillingRecipients()) {
                continue;
            }

            /* The same conditions the send button is checked against, re-read at firing time:
               a shop can lose its WhatsApp configuration, or a campaign its template, between
               being scheduled and coming due. */
            $reason = $campaign->unsendableReason();

            if ($reason !== null) {
                $this->stopCampaign($campaign, $reason);

                continue;
            }

            /* Claim the campaign before doing anything with it: whereNull('start_sending_at')
               above means a claimed row drops out of the next run's query, so a crash mid-loop
               cannot start the same campaign twice. */
            $campaign->update([
                'state'            => WhatsappCampaignStateEnum::SENDING,
                'start_sending_at' => Carbon::now()->utc(),
            ]);

            PrepareWhatsappCampaignRecipients::dispatch($campaign);
        }
    }

    /**
     * A campaign that came due unsendable ends rather than staying scheduled: leaving it
     * would have every later run pick it up again, and the operator would see a campaign
     * that is forever about to send. The reason is kept on the row so the state can be
     * explained after the fact.
     */
    private function stopCampaign(WhatsappCampaign $campaign, string $reason): void
    {
        $data = $campaign->data ?? [];

        Arr::set($data, 'stopped_reason', $reason);

        $campaign->update([
            'state'      => WhatsappCampaignStateEnum::STOPPED,
            'stopped_at' => Carbon::now()->utc(),
            'data'       => $data,
        ]);

        Log::warning('Scheduled WhatsApp campaign cannot be sent', [
            'whatsapp_campaign_id' => $campaign->id,
            'reason'               => $reason,
        ]);
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
