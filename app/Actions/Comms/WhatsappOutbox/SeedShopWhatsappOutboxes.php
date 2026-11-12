<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gives a shop one WhatsApp outbox per customer notification that can go out on WhatsApp.
 * They start suspended, because each needs a Meta approved UTILITY template before it can
 * send anything.
 */
class SeedShopWhatsappOutboxes
{
    use AsAction;

    /**
     * @return array<int, OutboxCodeEnum>
     */
    public static function codes(): array
    {
        return [
            OutboxCodeEnum::ORDER_CONFIRMATION,
            OutboxCodeEnum::DELIVERY_CONFIRMATION,
            OutboxCodeEnum::ORDER_PAYMENT_REMINDER,
            OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION,
            OutboxCodeEnum::OOS_NOTIFICATION,
            OutboxCodeEnum::PRE_ORDER_UPDATE,
            OutboxCodeEnum::CREDIT_BALANCE_NOTIFICATION_FOR_CUSTOMER,
            OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER,
            OutboxCodeEnum::REVIEW_REMINDER,
        ];
    }

    public function handle(Shop $shop): void
    {
        foreach (self::codes() as $code) {
            if (!in_array($shop->type->value, $code->shopTypes(), true)) {
                continue;
            }

            $whatsappOutbox = WhatsappOutbox::withTrashed()->where('shop_id', $shop->id)->where('code', $code->value)->first();

            if ($whatsappOutbox) {
                $whatsappOutbox->update(['name' => $code->label()]);

                continue;
            }

            WhatsappOutbox::create([
                'group_id'        => $shop->group_id,
                'organisation_id' => $shop->organisation_id,
                'shop_id'         => $shop->id,
                'code'            => $code,
                'name'            => $code->label(),
                'state'           => OutboxStateEnum::SUSPENDED,
            ]);
        }
    }

    public string $commandSignature = 'shop:seed_whatsapp_outboxes';

    public function asCommand(Command $command): int
    {
        $command->withProgressBar(Shop::all(), fn (Shop $shop) => $this->handle($shop));
        $command->newLine();

        return 0;
    }
}
