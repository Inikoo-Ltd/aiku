<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappBulkRun;

use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Enums\Comms\WhatsappDeliveryChannel\WhatsappDeliveryChannelStateEnum;
use App\Enums\CRM\Livechat\ChatMessageStateEnum;
use App\Models\Comms\WhatsappBulkRun;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Counts what a bulk run sent and failed, and closes it once none of its channels is still
 * waiting or sending.
 */
class UpdateWhatsappBulkRunState
{
    use AsObject;

    public function handle(WhatsappBulkRun $bulkRun): WhatsappBulkRun
    {
        $failed = ChatMessageStateEnum::FAILED->value;

        $counts = DB::table('whatsapp_recipients')
            ->join('meta_chat_messages', 'meta_chat_messages.id', '=', 'whatsapp_recipients.meta_chat_message_id')
            ->where('whatsapp_recipients.whatsapp_bulk_run_id', $bulkRun->id)
            ->selectRaw('count(*) filter (where meta_chat_messages.state <> ?) as sent', [$failed])
            ->selectRaw('count(*) filter (where meta_chat_messages.state = ?) as failed', [$failed])
            ->first();

        $modelData = [
            'number_sent'   => (int) ($counts->sent ?? 0),
            'number_failed' => (int) ($counts->failed ?? 0),
        ];

        $isStillSending = $bulkRun->deliveryChannels()
            ->whereIn('state', [
                WhatsappDeliveryChannelStateEnum::IN_PROCESS->value,
                WhatsappDeliveryChannelStateEnum::READY->value,
                WhatsappDeliveryChannelStateEnum::SENDING->value,
            ])
            ->exists();

        if (!$isStillSending && $bulkRun->state !== WhatsappBulkRunStateEnum::STOPPED) {
            $modelData['state']   = WhatsappBulkRunStateEnum::SENT;
            $modelData['sent_at'] = $bulkRun->sent_at ?? now();
        }

        $bulkRun->update($modelData);

        return $bulkRun;
    }
}
