<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

/**
 * How a campaign's audience is read from a request, shared by the picker that offers it and
 * the action that saves it.
 *
 * These two have to resolve the same audience from the same inputs or a save writes rows the
 * page never showed: the picker lists what the user chose from, and the save re-runs the
 * query rather than trusting a list of keys, so any drift between them is invisible until a
 * campaign goes out to the wrong people.
 */
trait WithWhatsappCampaignAudience
{
    /**
     * Defaults to the subscriber channel, the audience that opted in to being messaged.
     *
     * @return array<string, bool>
     */
    protected function readAudienceChannels(?array $requested, WhatsappCampaign $campaign): array
    {
        if (!is_array($requested)) {
            $requested = Arr::get($campaign->recipients_recipe ?? [], 'channels');
        }

        if (!is_array($requested) || empty(array_filter($requested, fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)))) {
            return GetWhatsappRecipientsQuery::DEFAULT_CHANNELS;
        }

        $channels = [];

        foreach (GetWhatsappRecipientsQuery::CHANNELS as $channel) {
            $channels[$channel] = filter_var(Arr::get($requested, $channel, false), FILTER_VALIDATE_BOOLEAN);
        }

        return $channels;
    }

    protected function readAudienceCustomerFilters(?array $filters, WhatsappCampaign $campaign): array
    {
        if (!is_array($filters)) {
            $filters = Arr::get($campaign->recipients_recipe ?? [], 'customer_filters', []);
        }

        if (!is_array($filters)) {
            return [];
        }

        return array_diff_key($filters, ['all_customers' => true]);
    }

    /**
     * The merge tags the campaign's template was written with, read from the same path
     * SendWhatsappDeliveryChannel fills them from. A recipient who cannot supply one of
     * them is not sent to, so the audience leaves them out rather than letting the campaign
     * count contacts it will only fail on.
     *
     * @return array<int, string>
     */
    protected function readTemplateTags(WhatsappCampaign $campaign): array
    {
        $tags = Arr::get($campaign->metaMessageTemplate?->data ?? [], 'merge_tags.body', []);

        return is_array($tags) ? $tags : [];
    }

    /**
     * @param  array<string, bool>  $channels
     *
     * @throws \Exception
     */
    protected function audienceQuery(WhatsappCampaign $campaign, array $channels, array $customerFilters): Builder
    {
        return FilterRecipientsByTemplateTags::run(
            GetWhatsappRecipientsQuery::run($campaign->shop, $channels, $customerFilters),
            $this->readTemplateTags($campaign)
        );
    }
}
