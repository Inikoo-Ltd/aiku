<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Comms;

use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $recipient_name
 * @property string $phone
 * @property int|null $meta_chat_message_id
 * @property string|null $delivered_at
 * @property string|null $read_at
 * @property string|null $meta_chat_session_ulid
 * @property mixed $metadata
 */
class WhatsappCampaignSentRecipientsResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status();

        return [
            'id'                     => $this->id,
            'name'                   => $this->recipient_name ?: $this->phone,
            'phone'                  => $this->phone,
            'status'                 => $status->value,
            'status_label'           => MetaTrackingEventTypeEnum::labels()[$status->value],
            'status_icon'            => MetaTrackingEventTypeEnum::typeIcon()[$status->value],
            'delivered_at'           => $this->formatDate($this->delivered_at),
            'read_at'                => $this->formatDate($this->read_at),
            'meta_chat_session_ulid' => $this->meta_chat_session_ulid,
        ];
    }

    /**
     * A recipient with no message never reached Meta: SendWhatsappDeliveryChannel keeps
     * meta_chat_message_id null on a failure so a re-run can retry it, which makes that
     * null the primary failure signal rather than missing data.
     */
    private function status(): MetaTrackingEventTypeEnum
    {
        $metadata = $this->metadata;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (!$this->meta_chat_message_id || Arr::get((array) $metadata, 'wa_status') === 'failed') {
            return MetaTrackingEventTypeEnum::FAILED;
        }

        if ($this->read_at) {
            return MetaTrackingEventTypeEnum::READ;
        }

        if ($this->delivered_at) {
            return MetaTrackingEventTypeEnum::DELIVERED;
        }

        return MetaTrackingEventTypeEnum::SENT;
    }

    /**
     * The joined columns arrive as raw timestamp strings rather than Carbon instances,
     * so they are parsed here instead of being formatted straight off the model.
     */
    private function formatDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->format('d F Y, H:i');
    }
}
