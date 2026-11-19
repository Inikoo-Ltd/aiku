<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\BackInStockNotification;

use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\BackInStockReminder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A back in stock reminder is answered by every channel the shop sends it on. Each channel
 * marks the reminders it handled, and a reminder is deleted only once no active channel is
 * still waiting to send it, so the email no longer takes the reminder away before WhatsApp
 * has seen it.
 */
class SettleBackInStockReminders
{
    use AsObject;

    /**
     * @param  array<int, int|string>  $reminderIds
     */
    public function handle(Shop $shop, array $reminderIds, string $notifiedColumn): void
    {
        $reminderIds = array_values(array_unique(array_filter(array_map('intval', $reminderIds))));

        if (!$reminderIds) {
            return;
        }

        BackInStockReminder::whereIn('id', $reminderIds)->update([$notifiedColumn => now()]);

        $isEmailActive    = $this->isEmailActive($shop);
        $isWhatsappActive = $this->isWhatsappActive($shop);

        $settledIds = BackInStockReminder::whereIn('id', $reminderIds)
            ->when($isEmailActive, fn ($query) => $query->whereNotNull(GetBackInStockCustomersQuery::EMAIL))
            ->when($isWhatsappActive, fn ($query) => $query->whereNotNull(GetBackInStockCustomersQuery::WHATSAPP))
            ->pluck('id')
            ->all();

        if ($settledIds) {
            BulkDeleteBackInStockReminder::run($settledIds);
        }
    }

    public function isEmailActive(Shop $shop): bool
    {
        return $shop->outboxes()
            ->where('code', OutboxCodeEnum::OOS_NOTIFICATION->value)
            ->where('state', OutboxStateEnum::ACTIVE->value)
            ->where('is_applicable', true)
            ->exists();
    }

    public function isWhatsappActive(Shop $shop): bool
    {
        $whatsappOutbox = $shop->whatsappOutboxes()->where('code', OutboxCodeEnum::OOS_NOTIFICATION->value)->first();

        return $whatsappOutbox && SendCustomerNotificationWhatsapp::make()->isSendable($whatsappOutbox);
    }
}
