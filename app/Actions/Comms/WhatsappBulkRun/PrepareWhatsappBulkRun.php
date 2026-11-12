<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappBulkRun;

use App\Actions\Comms\Outbox\BackInStockNotification\GetBackInStockCustomersQuery;
use App\Actions\Comms\Outbox\BackInStockNotification\SettleBackInStockReminders;
use App\Actions\Comms\Outbox\OutOfStockInOrder\GetOutOfStockInOrderCustomersQuery;
use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Models\Comms\WhatsappBulkRun;
use App\Models\Comms\WhatsappDeliveryChannel;
use App\Models\Comms\WhatsappOutbox;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Picks who a bulk WhatsApp outbox sends to this time, from the same customer query the email
 * of that code uses, keeps the customers who opted in to WhatsApp notifications, and stores
 * them in delivery channels of 50 that are each sent by their own job.
 */
class PrepareWhatsappBulkRun
{
    use AsAction;

    public const CHUNK_SIZE = 50;

    public string $jobQueue = 'whatsapp-send';

    public function handle(int $whatsappOutboxId): ?WhatsappBulkRun
    {
        $whatsappOutbox = WhatsappOutbox::find($whatsappOutboxId);
        $sender         = SendCustomerNotificationWhatsapp::make();

        if (!$whatsappOutbox || !$whatsappOutbox->isBulk() || !$sender->isSendable($whatsappOutbox)) {
            return null;
        }

        $shop = $whatsappOutbox->shop;

        if (!$shop->is_aiku || blank(Arr::get($shop->settings, 'whatsapp.phone_number_id'))) {
            return null;
        }

        $rows = $this->candidates($whatsappOutbox);

        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        $bulkRun = WhatsappBulkRun::create([
            'group_id'           => $whatsappOutbox->group_id,
            'organisation_id'    => $whatsappOutbox->organisation_id,
            'shop_id'            => $whatsappOutbox->shop_id,
            'whatsapp_outbox_id' => $whatsappOutbox->id,
            'state'              => WhatsappBulkRunStateEnum::IN_PROCESS,
        ]);

        $phonesInRun = [];
        $channels    = [];

        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            $customers = Customer::with('comms')->whereIn('id', $chunk->pluck('id'))->get()->keyBy('id');
            $recipients = [];

            foreach ($chunk as $row) {
                $customer = $customers->get($row->id);

                if (!$customer || !$sender->hasOptedIn($customer)) {
                    continue;
                }

                $phone = $sender->customerPhone($customer);
                $digits = $phone ? ltrim($phone, '+') : null;

                if (!$digits || isset($phonesInRun[$digits])) {
                    continue;
                }

                $phonesInRun[$digits] = true;

                $recipients[] = [
                    'customer' => $customer,
                    'phone'    => $digits,
                    'data'     => [
                        'product_ids' => $this->ids($row->product_ids ?? null),
                        'order_ids'   => $this->ids($row->order_ids ?? null),
                    ],
                ];
            }

            if ($recipients) {
                $channels[] = $this->storeChannel($bulkRun, $recipients);
            }

            if ($whatsappOutbox->code === OutboxCodeEnum::OOS_NOTIFICATION) {
                SettleBackInStockReminders::run(
                    $shop,
                    $chunk->flatMap(fn ($row) => $this->ids($row->reminder_ids ?? null))->all(),
                    GetBackInStockCustomersQuery::WHATSAPP
                );
            }
        }

        $bulkRun->update([
            'recipients_count'       => count($phonesInRun),
            'recipients_prepared_at' => now(),
            'state'                  => $channels ? WhatsappBulkRunStateEnum::SENDING : WhatsappBulkRunStateEnum::SENT,
            'sent_at'                => $channels ? null : now(),
        ]);

        foreach ($channels as $channel) {
            SendWhatsappBulkRunDeliveryChannel::dispatch($channel->id);
        }

        return $bulkRun;
    }

    /**
     * Read in full before anything is sent: settling back in stock reminders changes the rows
     * the query matches, and paging through it while doing so would skip customers.
     *
     * @return Collection<int, object>|null
     */
    private function candidates(WhatsappOutbox $whatsappOutbox): ?Collection
    {
        if ($whatsappOutbox->code === OutboxCodeEnum::OOS_NOTIFICATION) {
            return GetBackInStockCustomersQuery::run($whatsappOutbox->shop_id, GetBackInStockCustomersQuery::WHATSAPP)->get();
        }

        if (!$whatsappOutbox->interval) {
            return null;
        }

        $lastRunAt = $whatsappOutbox->bulkRuns()->latest('id')->value('created_at');
        $lastRunAt = $lastRunAt ? Carbon::parse($lastRunAt)->utc() : null;

        if ($lastRunAt && $lastRunAt->diffInHours(now(), true) < $whatsappOutbox->interval) {
            return null;
        }

        return GetOutOfStockInOrderCustomersQuery::run($whatsappOutbox->shop_id, $lastRunAt?->toDateTimeString())->get();
    }

    /**
     * @param  array<int, array{customer: Customer, phone: string, data: array<string, mixed>}>  $recipients
     */
    private function storeChannel(WhatsappBulkRun $bulkRun, array $recipients): WhatsappDeliveryChannel
    {
        $channel = WhatsappDeliveryChannel::create([
            'whatsapp_bulk_run_id' => $bulkRun->id,
            'number_messages'      => count($recipients),
            'state'                => WhatsappDeliveryChannelStateEnum::IN_PROCESS,
        ]);

        foreach ($recipients as $recipient) {
            $bulkRun->recipients()->create([
                'whatsapp_delivery_channel_id' => $channel->id,
                'recipient_type'               => class_basename(Customer::class),
                'recipient_id'                 => $recipient['customer']->id,
                'recipient_name'               => $recipient['customer']->name,
                'phone'                        => $recipient['phone'],
                'data'                         => $recipient['data'],
            ]);
        }

        $channel->update(['state' => WhatsappDeliveryChannelStateEnum::READY]);

        return $channel;
    }

    /**
     * @return array<int, int>
     */
    private function ids(?string $list): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $list)))));
    }
}
