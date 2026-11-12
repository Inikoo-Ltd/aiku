<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox\UI;

use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Livechat\ChatMessageStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The WhatsApp tab of the customer notification outboxes: the shop's WhatsApp outboxes,
 * each with its own state and template, and what went out in the last week.
 */
class GetWhatsappOutboxesTab
{
    use AsObject;

    /**
     * @return array{is_connected: bool, outboxes: array<int, array<string, mixed>>}
     */
    public function handle(Shop $shop): array
    {
        $whatsappOutboxes = $shop->whatsappOutboxes()
            ->with('metaMessageTemplate')
            ->orderBy('name')
            ->get();

        $stats = $this->lastWeekStats($whatsappOutboxes->pluck('id')->all());

        return [
            'is_connected' => filled(Arr::get($shop->settings, 'whatsapp.phone_number_id')),
            'outboxes'     => $whatsappOutboxes->map(fn (WhatsappOutbox $whatsappOutbox) => [
                'id'         => $whatsappOutbox->id,
                'slug'       => $whatsappOutbox->slug,
                'name'       => $whatsappOutbox->name,
                'code'       => $whatsappOutbox->code->value,
                'state'      => $whatsappOutbox->state->value,
                'state_icon' => OutboxStateEnum::icon()[$whatsappOutbox->state->value],
                'template'   => $whatsappOutbox->metaMessageTemplate ? self::templateData($whatsappOutbox->metaMessageTemplate) : null,
                'is_sending' => SendCustomerNotificationWhatsapp::make()->isSendable($whatsappOutbox),
                'sent_1w'    => Arr::get($stats, $whatsappOutbox->id.'.sent', 0),
                'failed_1w'  => Arr::get($stats, $whatsappOutbox->id.'.failed', 0),
                'route'      => [
                    'name'       => 'grp.org.shops.show.dashboard.comms.whatsapp_outboxes.show',
                    'parameters' => [
                        'organisation'   => $shop->organisation->slug,
                        'shop'           => $shop->slug,
                        'whatsappOutbox' => $whatsappOutbox->slug,
                    ],
                ],
            ])->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, language: string|null, status: string|null, category: string|null, is_usable: bool}
     */
    public static function templateData(MetaMessageTemplate $template): array
    {
        return [
            'id'        => $template->id,
            'name'      => $template->name,
            'language'  => $template->language,
            'status'    => $template->status,
            'category'  => $template->category ? strtoupper($template->category) : null,
            'is_usable' => SendCustomerNotificationWhatsapp::make()->isUsableTemplate($template),
        ];
    }

    /**
     * @param  array<int, int>  $whatsappOutboxIds
     * @return array<int, array{sent: int, failed: int}>
     */
    private function lastWeekStats(array $whatsappOutboxIds): array
    {
        if (!$whatsappOutboxIds) {
            return [];
        }

        return DB::table('meta_chat_messages')
            ->whereIn('whatsapp_outbox_id', $whatsappOutboxIds)
            ->where('created_at', '>=', now()->subWeek())
            ->select('whatsapp_outbox_id')
            ->selectRaw('count(*) filter (where state <> ?) as sent', [ChatMessageStateEnum::FAILED->value])
            ->selectRaw('count(*) filter (where state = ?) as failed', [ChatMessageStateEnum::FAILED->value])
            ->groupBy('whatsapp_outbox_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->whatsapp_outbox_id => ['sent' => (int) $row->sent, 'failed' => (int) $row->failed]])
            ->all();
    }
}
