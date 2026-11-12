<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappBulkRun;

use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Models\Comms\WhatsappBulkRun;
use App\Models\Comms\WhatsappDeliveryChannel;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sends one delivery channel of a bulk run, a message at a time, through the same utility
 * sender the order notifications use. A recipient already linked to a message is skipped, so
 * running a channel again never messages anyone twice.
 */
class SendWhatsappBulkRunDeliveryChannel
{
    use AsAction;

    public string $jobQueue = 'whatsapp-send';

    public function handle(int $whatsappDeliveryChannelId, bool $runOnlyInReady = true): void
    {
        $channel = WhatsappDeliveryChannel::find($whatsappDeliveryChannelId);

        if (!$channel || !$channel->whatsapp_bulk_run_id) {
            return;
        }

        if ($runOnlyInReady && $channel->state !== WhatsappDeliveryChannelStateEnum::READY) {
            return;
        }

        $bulkRun        = $channel->whatsappBulkRun;
        $whatsappOutbox = $bulkRun->whatsappOutbox;
        $sender         = SendCustomerNotificationWhatsapp::make();

        if ($bulkRun->state === WhatsappBulkRunStateEnum::STOPPED || !$sender->isSendable($whatsappOutbox)) {
            $channel->update(['state' => WhatsappDeliveryChannelStateEnum::STOPPED]);
            UpdateWhatsappBulkRunState::run($bulkRun);

            return;
        }

        $channel->update([
            'state'            => WhatsappDeliveryChannelStateEnum::SENDING,
            'start_sending_at' => now(),
        ]);

        if (!$bulkRun->start_sending_at) {
            $bulkRun->update(['start_sending_at' => now()]);
        }

        foreach ($bulkRun->recipients()->where('whatsapp_delivery_channel_id', $channel->id)->whereNull('meta_chat_message_id')->get() as $recipient) {
            if ($this->isStopped($bulkRun)) {
                $channel->update(['state' => WhatsappDeliveryChannelStateEnum::STOPPED]);

                return;
            }

            $customer = Customer::find($recipient->recipient_id);

            if (!$customer) {
                continue;
            }

            $orderIds = Arr::get($recipient->data ?? [], 'order_ids', []);

            $message = $sender->send(
                $whatsappOutbox,
                $customer,
                count($orderIds) === 1 ? Order::find($orderIds[0]) : null,
                [
                    'product_ids' => Arr::get($recipient->data ?? [], 'product_ids', []),
                    'order_ids'   => $orderIds,
                ],
                true
            );

            if ($message) {
                $recipient->update(['meta_chat_message_id' => $message->id]);
            }
        }

        $channel->update([
            'state'   => WhatsappDeliveryChannelStateEnum::SENT,
            'sent_at' => now(),
        ]);

        UpdateWhatsappBulkRunState::run($bulkRun->refresh());
    }

    private function isStopped(WhatsappBulkRun $bulkRun): bool
    {
        return $bulkRun->refresh()->state === WhatsappBulkRunStateEnum::STOPPED;
    }

    public string $commandSignature = 'whatsapp-bulk-run:send-channel {channel}';

    public function asCommand(Command $command): int
    {
        $this->handle((int) $command->argument('channel'), false);

        return 0;
    }
}
