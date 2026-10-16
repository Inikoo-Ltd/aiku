<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Wechat;

use App\Actions\Procurement\SupplierWechatConversation\FetchSupplierWechatConversationName;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Enums\Procurement\SupplierWechatConversation\SupplierWechatConversationKindEnum;
use App\Events\SupplierWechatMessageStored;
use App\Models\Procurement\SupplierMessage;
use App\Models\Procurement\SupplierWechatConversation;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreProcurementWechatMessage
{
    use AsAction;

    private const array MEDIA_TYPES = ['image', 'voice', 'video', 'file', 'emotion'];

    /**
     * The archive holds every chat of a licensed member of staff, colleagues included. Only chats
     * with someone outside the company are kept: WeCom gives people outside it ids starting with
     * wo or wm, and members of staff their plain account names.
     */
    public static function isExternal(string $wecomId): bool
    {
        return str_starts_with($wecomId, 'wo') || str_starts_with($wecomId, 'wm');
    }

    /**
     * @param  array<string, mixed>  $message  one decrypted archive message
     */
    public function handle(Organisation $organisation, array $message): ?SupplierMessage
    {
        $wechatMessageId = (string) Arr::get($message, 'msgid');

        if (Arr::get($message, 'action') !== 'send' || $wechatMessageId === '' || SupplierMessage::where('wechat_message_id', $wechatMessageId)->exists()) {
            return null;
        }

        $from   = (string) Arr::get($message, 'from');
        $toList = array_values(array_filter(array_map('strval', (array) Arr::get($message, 'tolist', []))));

        if (! collect([$from, ...$toList])->contains(fn (string $wecomId) => self::isExternal($wecomId))) {
            return null;
        }

        $isInbound    = self::isExternal($from);
        $conversation = $this->conversation($organisation, $message, $from, $toList, $isInbound);

        if ($conversation->wasRecentlyCreated) {
            FetchSupplierWechatConversationName::dispatch($conversation);
        }
        $text         = self::text($message);
        $user         = $isInbound ? null : $this->staffUser($organisation, $from);

        $supplierMessage = SupplierMessage::create([
            ...SupplierMessage::counterpartAttributes($conversation->counterpart()),
            'group_id'                        => $organisation->group_id,
            'organisation_id'                 => $organisation->id,
            'supplier_wechat_conversation_id' => $conversation->id,
            'channel'                         => SupplierMessageChannelEnum::WECHAT,
            'wechat_message_id'               => $wechatMessageId,
            'user_id'                         => $user?->id,
            'direction'                       => $isInbound ? SupplierMessageDirectionEnum::INBOUND : SupplierMessageDirectionEnum::OUTBOUND,
            'from_address'                    => $from,
            'from_name'                       => $isInbound ? ($conversation->kind === SupplierWechatConversationKindEnum::SINGLE ? $conversation->displayName() : $from) : ($user?->contact_name ?? $from),
            'to'                              => array_map(fn (string $wecomId) => ['name' => null, 'address' => $wecomId], $toList),
            'snippet'                         => mb_substr($text, 0, 200),
            'body_text'                       => $text,
            'attachments'                     => self::attachments($message),
            'sent_at'                         => Carbon::createFromTimestampMs((int) Arr::get($message, 'msgtime', now()->getTimestampMs())),
        ]);

        if (! $conversation->last_message_at || $supplierMessage->sent_at->gte($conversation->last_message_at)) {
            $conversation->update([
                'last_message_at'      => $supplierMessage->sent_at,
                'last_message_snippet' => $supplierMessage->snippet,
            ]);
        }

        SupplierWechatMessageStored::dispatch($supplierMessage);

        return $supplierMessage;
    }

    /**
     * A group chat is one conversation whoever writes in it. A one-to-one chat belongs to the pair:
     * the same supplier talking to two members of staff is two conversations, as it is in WeCom.
     *
     * @param  array<string, mixed>  $message
     * @param  array<int, string>  $toList
     */
    private function conversation(Organisation $organisation, array $message, string $from, array $toList, bool $isInbound): SupplierWechatConversation
    {
        $roomId = (string) Arr::get($message, 'roomid', '');

        if ($roomId !== '') {
            return SupplierWechatConversation::firstOrCreate(
                ['organisation_id' => $organisation->id, 'room_id' => $roomId],
                ['group_id' => $organisation->group_id, 'kind' => SupplierWechatConversationKindEnum::GROUP]
            );
        }

        $externalUserId = $isInbound ? $from : collect($toList)->first(fn (string $wecomId) => self::isExternal($wecomId));
        $staffWecomId   = $isInbound ? collect($toList)->first(fn (string $wecomId) => ! self::isExternal($wecomId)) : $from;

        return SupplierWechatConversation::firstOrCreate(
            ['organisation_id' => $organisation->id, 'staff_wecom_userid' => $staffWecomId, 'external_userid' => $externalUserId],
            ['group_id' => $organisation->group_id, 'kind' => SupplierWechatConversationKindEnum::SINGLE]
        );
    }

    private function staffUser(Organisation $organisation, string $staffWecomId): ?User
    {
        $userId = Arr::get($organisation->settings, 'procurement.wechat.staff.'.$staffWecomId);

        return $userId ? User::find($userId) : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public static function text(array $message): string
    {
        $type = (string) Arr::get($message, 'msgtype');

        return trim(match ($type) {
            'text'       => (string) Arr::get($message, 'text.content'),
            'markdown'   => (string) Arr::get($message, 'info.content', Arr::get($message, 'markdown.content')),
            'file'       => '['.__('File').'] '.Arr::get($message, 'file.filename'),
            'link'       => trim(Arr::get($message, 'link.title').' '.Arr::get($message, 'link.link_url')),
            'location'   => '['.__('Location').'] '.Arr::get($message, 'location.address'),
            'weapp'      => '['.__('Mini program').'] '.Arr::get($message, 'weapp.title'),
            'card'       => '['.__('Contact card').'] '.Arr::get($message, 'card.corpname'),
            'mixed'      => collect(Arr::get($message, 'mixed.item', []))
                ->map(fn (array $item) => $item['type'] === 'text' ? (string) Arr::get(json_decode((string) $item['content'], true), 'content') : '['.$item['type'].']')
                ->implode(' '),
            'image'      => '['.__('Image').']',
            'voice'      => '['.__('Voice message').']',
            'video'      => '['.__('Video').']',
            'emotion'    => '['.__('Sticker').']',
            'chatrecord' => '['.__('Forwarded chat history').']',
            default      => '['.$type.']',
        });
    }

    /**
     * Files stay with WeCom until they are downloaded, which needs the library again, so only
     * what identifies them is kept here.
     *
     * @param  array<string, mixed>  $message
     * @return array<int, array{sdk_file_id: string, name: string, mime_type: string, size: int}>
     */
    public static function attachments(array $message): array
    {
        $type = (string) Arr::get($message, 'msgtype');
        $node = (array) Arr::get($message, $type, []);

        if (! in_array($type, self::MEDIA_TYPES, true) || blank(Arr::get($node, 'sdkfileid'))) {
            return [];
        }

        return [[
            'sdk_file_id' => $node['sdkfileid'],
            'name'        => (string) (Arr::get($node, 'filename') ?? $type.'.'.Arr::get($node, 'fileext', match ($type) {
                'image'   => 'jpg',
                'voice'   => 'amr',
                'video'   => 'mp4',
                'emotion' => 'gif',
                default   => 'bin',
            })),
            'mime_type'   => match ($type) {
                'image'   => 'image/jpeg',
                'voice'   => 'audio/amr',
                'video'   => 'video/mp4',
                'emotion' => 'image/gif',
                default   => 'application/octet-stream',
            },
            'size'        => (int) (Arr::get($node, 'filesize') ?? Arr::get($node, 'voice_size') ?? 0),
        ]];
    }
}
