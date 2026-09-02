<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * One definition of what a recipient's delivery status is, in SQL.
 *
 * The same precedence is expressed in PHP by WhatsappCampaignSentRecipientsResource::status();
 * these two must agree, or a status tab narrows to a different set of rows than the one it
 * counts, and the campaign stats disagree with the list they summarise.
 */
trait WithWhatsappRecipientStatusQuery
{
    protected function recipientStatusBaseQuery(WhatsappCampaign $campaign): EloquentBuilder
    {
        return WhatsappRecipient::query()
            ->where('whatsapp_recipients.whatsapp_campaign_id', $campaign->id)
            ->leftJoin('meta_chat_messages', 'meta_chat_messages.id', '=', 'whatsapp_recipients.meta_chat_message_id')
            ->leftJoin('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id');
    }

    /**
     * The buckets are mutually exclusive: a read message is not also counted as delivered,
     * so the counts sum to the number of recipients rather than double-counting a funnel.
     *
     * Meta can deliver the lifecycle out of order, so a message read without a prior
     * delivered callback has read_at but no delivered_at; it counts as read only.
     */
    protected function recipientStatusCondition(Builder|EloquentBuilder $query, string $status): void
    {
        match ($status) {
            MetaTrackingEventTypeEnum::FAILED->value => $query->where(function ($query) {
                $query->whereNull('whatsapp_recipients.meta_chat_message_id')
                    ->orWhereRaw("meta_chat_messages.metadata->>'wa_status' = 'failed'");
            }),
            MetaTrackingEventTypeEnum::READ->value => $query->whereNotNull('whatsapp_recipients.meta_chat_message_id')
                ->whereNotNull('meta_chat_messages.read_at')
                ->whereRaw("coalesce(meta_chat_messages.metadata->>'wa_status', '') <> 'failed'"),
            MetaTrackingEventTypeEnum::DELIVERED->value => $query->whereNotNull('whatsapp_recipients.meta_chat_message_id')
                ->whereNotNull('meta_chat_messages.delivered_at')
                ->whereNull('meta_chat_messages.read_at')
                ->whereRaw("coalesce(meta_chat_messages.metadata->>'wa_status', '') <> 'failed'"),
            /* The resource's fallthrough case: the message reached Meta and nothing further
               has come back about it. */
            MetaTrackingEventTypeEnum::SENT->value => $query->whereNotNull('whatsapp_recipients.meta_chat_message_id')
                ->whereNull('meta_chat_messages.delivered_at')
                ->whereNull('meta_chat_messages.read_at')
                ->whereRaw("coalesce(meta_chat_messages.metadata->>'wa_status', '') <> 'failed'"),
            default => $query->whereRaw('1 = 0'),
        };
    }

    protected function countRecipientsWithStatus(WhatsappCampaign $campaign, string $status): int
    {
        $query = $this->recipientStatusBaseQuery($campaign);
        $this->recipientStatusCondition($query, $status);

        return $query->count();
    }
}
