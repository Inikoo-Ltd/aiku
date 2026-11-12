<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox\UI;

use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Livechat\ChatMessageStateEnum;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWhatsappOutboxShowcase
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(WhatsappOutbox $whatsappOutbox, bool $canEdit): array
    {
        $shop     = $whatsappOutbox->shop;
        $template = $whatsappOutbox->metaMessageTemplate;
        $outbox   = $shop->outboxes()->where('code', $whatsappOutbox->code->value)->first();

        return [
            'id'           => $whatsappOutbox->id,
            'name'         => $whatsappOutbox->name,
            'code'         => $whatsappOutbox->code->value,
            'state'        => $whatsappOutbox->state->value,
            'is_active'    => $whatsappOutbox->state === OutboxStateEnum::ACTIVE,
            'is_sending'   => SendCustomerNotificationWhatsapp::make()->isSendable($whatsappOutbox),
            'is_connected' => filled(Arr::get($shop->settings, 'whatsapp.phone_number_id')),
            'can_edit'     => $canEdit,
            'last_sent_at' => $whatsappOutbox->last_sent_at,
            'is_bulk'      => $whatsappOutbox->isBulk(),
            'interval'     => $whatsappOutbox->interval,
            'needs_interval' => $whatsappOutbox->code === OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION,
            'template'     => $template ? GetWhatsappOutboxesTab::templateData($template) + [
                'body' => $this->templateBody($template),
                'tags' => Arr::get($template->data ?? [], 'merge_tags.body', []),
            ] : null,
            'templates'    => MetaMessageTemplate::where('shop_id', $shop->id)
                ->whereNotNull('template_id')
                ->orderBy('name')
                ->get()
                ->map(fn (MetaMessageTemplate $option) => GetWhatsappOutboxesTab::templateData($option))
                ->values()
                ->all(),
            'stats'        => $this->stats($whatsappOutbox),
            'email_outbox' => $outbox ? [
                'name'  => $outbox->name,
                'state' => $outbox->state->value,
                'route' => [
                    'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.show',
                    'parameters' => [$shop->organisation->slug, $shop->slug, $outbox->slug],
                ],
            ] : null,
            'update_route' => [
                'name'       => 'grp.models.shop.whatsapp_outboxes.update',
                'parameters' => [
                    'shop'           => $shop->id,
                    'whatsappOutbox' => $whatsappOutbox->id,
                ],
                'method'     => 'patch',
            ],
        ];
    }

    private function templateBody(MetaMessageTemplate $template): ?string
    {
        return Arr::get(collect(Arr::get($template->data ?? [], 'components', []))->firstWhere('type', 'BODY') ?? [], 'text');
    }

    /**
     * @return array{sent: int, delivered: int, read: int, failed: int, sent_1w: int, failed_1w: int}
     */
    private function stats(WhatsappOutbox $whatsappOutbox): array
    {
        $failed = ChatMessageStateEnum::FAILED->value;

        $row = DB::table('meta_chat_messages')
            ->where('whatsapp_outbox_id', $whatsappOutbox->id)
            ->selectRaw('count(*) filter (where state <> ?) as sent', [$failed])
            ->selectRaw('count(*) filter (where delivered_at is not null) as delivered')
            ->selectRaw('count(*) filter (where read_at is not null) as read')
            ->selectRaw('count(*) filter (where state = ?) as failed', [$failed])
            ->selectRaw('count(*) filter (where state <> ? and created_at >= ?) as sent_1w', [$failed, now()->subWeek()])
            ->selectRaw('count(*) filter (where state = ? and created_at >= ?) as failed_1w', [$failed, now()->subWeek()])
            ->first();

        return [
            'sent'      => (int) ($row->sent ?? 0),
            'delivered' => (int) ($row->delivered ?? 0),
            'read'      => (int) ($row->read ?? 0),
            'failed'    => (int) ($row->failed ?? 0),
            'sent_1w'   => (int) ($row->sent_1w ?? 0),
            'failed_1w' => (int) ($row->failed_1w ?? 0),
        ];
    }
}
