<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

trait WithWhatsappCampaignSendable
{
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
     * The conditions a campaign must meet before it can be sent or scheduled.
     * Mirrors the isConfigured/recipients checks the UI disables its buttons on,
     * so a hand-rolled request gets the same answer as the page.
     *
     * @throws ValidationException
     */
    protected function assertSendable(WhatsappCampaign $campaign): void
    {
        if (!$campaign->meta_message_template_id) {
            throw ValidationException::withMessages([
                'campaign' => __('Choose a template before sending this campaign.'),
            ]);
        }

        if ($campaign->recipients_count < 1) {
            throw ValidationException::withMessages([
                'campaign' => __('This campaign has no recipients.'),
            ]);
        }

        if (blank(Arr::get($campaign->shop->settings, 'whatsapp.phone_number_id'))) {
            throw ValidationException::withMessages([
                'campaign' => __('WhatsApp is not configured for this shop.'),
            ]);
        }
    }
}
