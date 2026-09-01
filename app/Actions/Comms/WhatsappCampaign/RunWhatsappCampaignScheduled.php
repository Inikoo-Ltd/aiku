<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Comms\WhatsappCampaign;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;
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

    public function asCommand(): void
    {
        $this->run();
    }
}
