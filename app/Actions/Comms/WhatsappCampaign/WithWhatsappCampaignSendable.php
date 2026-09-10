<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Comms\WhatsappCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait WithWhatsappCampaignSendable
{
    /**
     * Composing is only allowed while the campaign is still being put together. Once it is
     * scheduled, sending, sent, cancelled or stopped its content is frozen, a scheduled
     * campaign must be cancelled first so the audience it goes out to is the one chosen.
     *
     * @throws ValidationException
     */
    protected function assertEditable(WhatsappCampaign $campaign): void
    {
        if ($campaign->isUnsent()) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __('This campaign can no longer be edited.'),
        ]);
    }

    /**
     * A campaign is publishable once it is composed and has an audience, so every update
     * re-evaluates readiness: the template and the recipients are saved by separate
     * requests and either one can be the last piece to arrive.
     *
     * Only the draft/ready pair is touched. Once a campaign is scheduled, sending or
     * finished it is left alone, an in flight send must never be pulled backwards.
     */
    protected function syncReadyState(WhatsappCampaign $campaign): void
    {
        $isReady = $this->isCampaignReady($campaign);

        if ($isReady && $campaign->state == WhatsappCampaignStateEnum::IN_PROCESS) {
            $this->update($campaign, [
                'state'    => WhatsappCampaignStateEnum::READY,
                'ready_at' => now(),
            ]);

            return;
        }

        if (!$isReady && $campaign->state == WhatsappCampaignStateEnum::READY) {
            $this->update($campaign, [
                'state'    => WhatsappCampaignStateEnum::IN_PROCESS,
                'ready_at' => null,
            ]);
        }
    }

    /**
     * What the READY state means: the campaign is composed and has an audience.
     *
     * Shop level WhatsApp configuration is deliberately excluded, it belongs to the shop
     * rather than the campaign and would otherwise un-ready every campaign at once when a
     * shop setting changes. It stays a send time condition in assertSendable().
     */
    protected function isCampaignReady(WhatsappCampaign $campaign): bool
    {
        return $campaign->meta_message_template_id && $campaign->recipients_count >= 1;
    }

    /**
     * The conditions a campaign must meet before it can be sent or scheduled, as the
     * request that asked for it hears them.
     *
     * @throws ValidationException
     */
    protected function assertSendable(WhatsappCampaign $campaign): void
    {
        $reason = $campaign->unsendableReason();

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'campaign' => $reason,
            ]);
        }
    }

    /**
     * Starts the fill over, abandoning whatever is already walking. The generation is the whole
     * mechanism: an in flight chain reads it at the top of each slice and stops once it no
     * longer matches, so the audience is only ever resolved by the run that matches the
     * selection the user last saved.
     *
     * A fresh walk rather than a top up of the new rows: after a template change the snapshots
     * already written answer the old template, and telling those apart costs more than
     * resolving them again.
     */
    protected function restartRecipientFill(WhatsappCampaign $campaign, bool $discardSnapshots = false): void
    {
        if ($discardSnapshots) {
            /* Through the query builder rather than the model: updated_at is the mark
               StoreWhatsappCampaignRecipients sweeps on, and touching it here would make a
               discarded snapshot look like a freshly saved selection. */
            DB::table('whatsapp_recipients')
                ->where('whatsapp_campaign_id', $campaign->id)
                ->whereNull('whatsapp_delivery_channel_id')
                ->update(['data' => null]);
        }

        $data = $campaign->data ?? [];

        Arr::set($data, 'fill_generation', $campaign->fillGeneration() + 1);
        Arr::set($data, 'fill_started_at', now()->toIso8601String());

        $this->update($campaign, ['data' => $data]);

        $campaign->refresh();

        FillWhatsappRecipientData::dispatch($campaign, null, $campaign->fillGeneration());
    }
}
