<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Comms\WhatsappCampaign\Hydrators\WhatsappCampaignHydrateStats;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Models\Comms\WhatsappCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Closes a campaign once every one of its channels has finished, whether they sent or
 * stopped. A channel that stopped, on missing WhatsApp credentials or a deleted template,
 * counts as finished: the campaign it belongs to did not send, and leaving it in SENDING
 * would keep it there forever waiting for a channel that will never move again.
 *
 * A guard rather than a command: each channel calls it as it completes and all but the
 * last one falls straight back out, so no job has to coordinate the others.
 *
 * @return array{msg: string}
 */
class UpdateWhatsappCampaignSentState
{
    use AsAction;

    public function handle(WhatsappCampaign $campaign): array
    {
        if ($campaign->state == WhatsappCampaignStateEnum::STOPPED) {
            return ['msg' => 'campaign stopped'];
        }

        if ($campaign->deliveryChannels()->count() == 0) {
            return ['msg' => 'no channels found'];
        }

        $unfinished = $campaign->deliveryChannels()
            ->whereNotIn('state', [
                WhatsappDeliveryChannelStateEnum::SENT,
                WhatsappDeliveryChannelStateEnum::STOPPED,
            ])
            ->count();

        if ($unfinished > 0) {
            return ['msg' => 'channels still processing '.$unfinished];
        }

        if ($campaign->deliveryChannels()->where('state', WhatsappDeliveryChannelStateEnum::STOPPED)->exists()) {
            $campaign->update([
                'state'      => WhatsappCampaignStateEnum::STOPPED,
                'stopped_at' => now(),
            ]);

            WhatsappCampaignHydrateStats::dispatch($campaign->id);

            return ['msg' => 'campaign stopped'];
        }

        $campaign->update([
            'state'   => WhatsappCampaignStateEnum::SENT,
            'sent_at' => $campaign->deliveryChannels()->max('sent_at'),
        ]);

        /* A finished campaign settles on correct totals even if a delivery webhook was
           missed while it was still sending. */
        WhatsappCampaignHydrateStats::dispatch($campaign->id);

        return ['msg' => 'campaign sent'];
    }

    public string $commandSignature = 'whatsapp-campaign:sent-state {campaign}';

    public function asCommand(Command $command): int
    {
        $campaign = WhatsappCampaign::where('slug', $command->argument('campaign'))->first();

        if (!$campaign) {
            $command->error('Campaign not found');

            return 1;
        }

        $command->line(Arr::get($this->handle($campaign), 'msg', ''));

        return 0;
    }
}
