<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Whatsapp;

use App\Actions\Procurement\SupplierMessage\RouteSupplierMessage;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreIncomingProcurementWhatsappMessage
{
    use AsAction;

    public static function organisationFor(string $phoneNumberId): ?Organisation
    {
        if ($phoneNumberId === '') {
            return null;
        }

        return Organisation::where('settings->procurement->whatsapp->phone_number_id', $phoneNumberId)->first();
    }

    /**
     * Files stay with Meta and are fetched when someone opens them, as email attachments stay in
     * Gmail. Meta keeps media for thirty days, which covers answering the message.
     *
     * @param  array<string, mixed>  $value
     */
    public function handle(array $value): int
    {
        $organisation = self::organisationFor((string) Arr::get($value, 'metadata.phone_number_id'));

        if (! $organisation) {
            return 0;
        }

        $stored = 0;

        foreach (Arr::get($value, 'messages', []) as $message) {
            $waMessageId = (string) Arr::get($message, 'id');

            if ($waMessageId === '' || SupplierMessage::where('whatsapp_message_id', $waMessageId)->exists()) {
                continue;
            }

            $type = (string) Arr::get($message, 'type');

            if ($type === 'reaction') {
                continue;
            }

            $phone = RouteSupplierMessage::phoneDigits((string) Arr::get($message, 'from'));
            $node  = (array) Arr::get($message, $type, []);
            $text  = Arr::get($message, 'text.body') ?? Arr::get($node, 'caption') ?? Arr::get($message, 'button.text');

            [$counterpart, $routedBy] = RouteSupplierMessage::make()->byPhone($organisation, $phone);

            SupplierMessage::create([
                ...SupplierMessage::counterpartAttributes($counterpart),
                'group_id'            => $organisation->group_id,
                'organisation_id'     => $organisation->id,
                'channel'             => SupplierMessageChannelEnum::WHATSAPP,
                'whatsapp_message_id' => $waMessageId,
                'phone_number'        => $phone,
                'direction'           => SupplierMessageDirectionEnum::INBOUND,
                'routed_by'           => $routedBy,
                'from_address'        => '+'.$phone,
                'from_name'           => Arr::get($value, 'contacts.0.profile.name'),
                'snippet'             => $text ? mb_substr($text, 0, 200) : ($type !== 'text' ? '['.$type.']' : null),
                'body_text'           => $text,
                'attachments'         => filled(Arr::get($node, 'id')) ? [[
                    'media_id'  => Arr::get($node, 'id'),
                    'name'      => Arr::get($node, 'filename') ?? $type.'.'.str(Arr::get($node, 'mime_type', 'application/octet-stream'))->afterLast('/')->before(';'),
                    'mime_type' => Arr::get($node, 'mime_type', 'application/octet-stream'),
                    'size'      => 0,
                ]] : [],
                'sent_at'             => Carbon::createFromTimestamp((int) Arr::get($message, 'timestamp', now()->timestamp)),
            ]);

            $stored++;
        }

        return $stored;
    }
}
