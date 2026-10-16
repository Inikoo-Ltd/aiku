<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Models\Procurement\SupplierMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shaped like a staff chat message so the messaging panel shows it with the same thread, with
 * nothing that can be answered, reacted to or translated: the chat itself happens in WeCom.
 *
 * @property SupplierMessage $resource
 */
class SupplierWechatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id'                => $message->id,
            'kind'              => 'supplier_wechat',
            'conversation_ulid' => $message->supplierWechatConversation?->ulid,
            'organisation_id'   => $message->organisation_id,
            'user_id'           => null,
            'user_name'         => (string) ($message->from_name ?? $message->from_address),
            'is_from_supplier'  => $message->direction === SupplierMessageDirectionEnum::INBOUND,
            'parent_id'         => null,
            'body'              => (string) $message->body_text,
            'mentions'          => [],
            'mentioned_me'      => false,
            'language_id'       => null,
            'translations'      => [],
            'reactions'         => [],
            'image'             => null,
            'gif_url'           => null,
            'attachments'       => collect($message->attachments)->map(fn (array $attachment) => ['name' => $attachment['name'], 'mime_type' => $attachment['mime_type']])->all(),
            'created_at'        => $message->sent_at->toIso8601String(),
        ];
    }
}
