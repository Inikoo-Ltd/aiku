<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\MetaChatSession\ReopenMetaChatSession;
use App\Actions\Chat\MetaChatSession\SetMetaChatMessageReaction;
use App\Actions\Chat\MetaChatSession\StoreMetaChatMessage;
use App\Actions\Chat\MetaChatSession\StoreMetaChatSession;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreIncomingWhatsappMessage
{
    use AsAction;
    use WithWhatsappCredentials;

    public string $jobQueue = 'urgent';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a WhatsApp webhook
     */
    public function asJob(array $value): void
    {
        $this->handle($value);
    }

    /**
     * @param  array<string, mixed>  $value
     *
     * @throws \Throwable
     */
    public function handle(array $value): void
    {
        $phoneNumberId = (string) Arr::get($value, 'metadata.phone_number_id');

        $shop = Shop::whereJsonContains('settings->whatsapp->phone_number_id', $phoneNumberId)->first();

        if (!$shop) {
            Log::warning('WhatsApp message for unknown phone_number_id', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        $metaChannel = MetaChannel::where('code', 'whatsapp')->first();

        if (!$metaChannel) {
            Log::warning('WhatsApp meta channel is not configured');

            return;
        }

        foreach (Arr::get($value, 'messages', []) as $message) {
            $this->storeMessage($shop, $metaChannel, $value, $message);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $message
     */
    protected function storeMessage(Shop $shop, MetaChannel $metaChannel, array $value, array $message): void
    {
        $waMessageId = (string) Arr::get($message, 'id');

        if ($waMessageId === '') {
            return;
        }

        if (MetaChatMessage::where('meta_message_id', $waMessageId)->exists()) {
            return;
        }

        $digits      = preg_replace('/\D/', '', (string) Arr::get($message, 'from'));
        $profileName = Arr::get($value, 'contacts.0.profile.name');

        $metaChatSession = MetaChatSession::where('meta_channel_id', $metaChannel->id)
            ->where('shop_id', $shop->id)
            ->whereIn('phone_number', ['+'.$digits, $digits])
            ->latest('id')
            ->first();

        if (!$metaChatSession) {
            $customer = $this->findCustomer($shop, $digits);

            $metaChatSession = StoreMetaChatSession::run([
                'shop_id'      => $shop->id,
                'customer_id'  => $customer?->id,
                'phone_number' => '+'.$digits,
                'name'         => $profileName,
            ]);
        } elseif ($metaChatSession->status === ChatSessionStatusEnum::CLOSED) {
            $metaChatSession = ReopenMetaChatSession::make()->reopenToWaiting($metaChatSession);
        }

        $type     = (string) Arr::get($message, 'type');
        $waNode   = Arr::get($message, $type);
        $isMedia  = in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true);

        if ($type === 'reaction') {
            $this->storeReaction($metaChatSession, $waMessageId, (array) $waNode);
            $metaChatSession->update(['last_visitor_message_at' => now()]);

            return;
        }

        $quotedWaMessageId = (string) Arr::get($message, 'context.id');

        $metaChatMessage = StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => $waMessageId,
            'message_type'    => $this->messageType($type),
            'sender_type'     => ChatSenderTypeEnum::GUEST,
            'message_text'    => $this->messageText($type, $message, $waNode),
            'replied_to_id'   => $this->resolveQuotedMessageId($metaChatSession, $quotedWaMessageId),
            'metadata'        => [
                'wa_type'      => $type,
                'profile_name' => $profileName,
                'wa_payload'   => $type !== 'text' ? $waNode : null,
                'wa_context'   => Arr::get($message, 'context'),
                'wa_errors'    => Arr::get($message, 'errors'),
            ],
        ]);

        // Meta waits for this response and retries a slow one, so pulling the binary —
        // a video can be tens of megabytes — happens after the webhook is answered.
        if ($isMedia) {
            DownloadWhatsappMedia::dispatch($metaChatMessage, $shop);
        }

        $metaChatSession->update(['last_visitor_message_at' => now()]);

        $metaChatMessage = $metaChatMessage->fresh(['attachment', 'metaChatSession']);

        if ($type === 'text') {
            $this->raiseTicketIfCommand($shop, $metaChatMessage, $digits);
        }

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage);
        BroadcastMetaChatListEvent::dispatch($metaChatMessage, $metaChatSession->fresh());
    }

    protected function resolveQuotedMessageId(MetaChatSession $metaChatSession, string $quotedWaMessageId): ?int
    {
        if ($quotedWaMessageId === '') {
            return null;
        }

        return $metaChatSession->messages()
            ->where('meta_message_id', $quotedWaMessageId)
            ->value('id');
    }


    protected function messageType(string $type): ChatMessageTypeEnum
    {
        return match (true) {
            in_array($type, DownloadWhatsappMedia::IMAGE_TYPES, true) => ChatMessageTypeEnum::IMAGE,
            in_array($type, DownloadWhatsappMedia::MEDIA_TYPES, true) => ChatMessageTypeEnum::FILE,
            default                                                   => ChatMessageTypeEnum::TEXT,
        };
    }

    /**
     * WhatsApp puts the readable part of a message in a different place for every type.
     * Anything without one would otherwise be stored blank, which is how a customer
     * tapping a template button ended up as an empty bubble.
     *
     * @param  array<string, mixed>  $message
     */
    protected function messageText(string $type, array $message, mixed $waNode): ?string
    {
        return match ($type) {
            'text'        => Arr::get($message, 'text.body'),
            'button'      => Arr::get($message, 'button.text'),
            'interactive' => $this->interactiveText((array) $waNode),
            'location'    => $this->locationText((array) $waNode),
            'contacts'    => $this->contactsText((array) $waNode),
            // WhatsApp refuses to deliver the content of some types — polls among them —
            // and sends only their name. Saying so beats an empty bubble the agent cannot
            // tell apart from a delivery bug.
            'unsupported' => __('Unsupported message: :type', [
                'type' => Arr::get($waNode, 'raw_type') ?: Arr::get($waNode, 'type', 'unknown'),
            ]),
            default       => Arr::get($waNode, 'caption'),
        };
    }

    /**
     * @param  array<string, mixed>  $interactive
     */
    protected function interactiveText(array $interactive): ?string
    {
        $reply = Arr::get($interactive, 'button_reply') ?? Arr::get($interactive, 'list_reply');

        return Arr::get((array) $reply, 'title');
    }

    /**
     * @param  array<string, mixed>  $location
     */
    protected function locationText(array $location): ?string
    {
        $label = collect([Arr::get($location, 'name'), Arr::get($location, 'address')])
            ->filter()
            ->implode(' — ');

        if ($label !== '') {
            return $label;
        }

        $latitude  = Arr::get($location, 'latitude');
        $longitude = Arr::get($location, 'longitude');

        return $latitude && $longitude ? $latitude.', '.$longitude : null;
    }

    /**
     * @param  array<string, mixed>  $contacts
     */
    protected function contactsText(array $contacts): ?string
    {
        $names = collect($contacts)
            ->map(fn ($contact) => Arr::get((array) $contact, 'name.formatted_name'))
            ->filter();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $reaction
     */
    protected function storeReaction(MetaChatSession $metaChatSession, string $waMessageId, array $reaction): void
    {
        $targetId = (string) Arr::get($reaction, 'message_id');

        $target = $metaChatSession->messages()
            ->where('meta_message_id', $targetId)
            ->first();

        if (!$target) {
            Log::info('WhatsApp reaction for unknown message', [
                'meta_chat_session_id' => $metaChatSession->id,
                'target_message_id'    => $targetId,
            ]);

            return;
        }

        SetMetaChatMessageReaction::run(
            $target,
            ChatSenderTypeEnum::GUEST->value,
            null,
            (string) Arr::get($reaction, 'emoji', ''),
            $waMessageId
        );
    }

    /**
     * Staff can raise a ticket from their phone by opening a message with `/ticket`.
     * A number that belongs to no staff user is ignored in silence: answering it would
     * turn the shop's public number into an oracle for whether a phone is staff.
     */
    protected function raiseTicketIfCommand(Shop $shop, MetaChatMessage $metaChatMessage, string $digits): void
    {
        $body = $this->ticketCommandBody((string) $metaChatMessage->message_text);

        if ($body === null) {
            return;
        }

        $reporter = $this->staffReporter($shop, $digits);

        if (!$reporter) {
            return;
        }

        // A ticket that cannot be raised must not fail the queued webhook job: Meta would
        // redeliver the whole payload and the customer's message would be stored twice.
        try {
            StoreTicketFromWhatsapp::run($shop, $metaChatMessage, $reporter, $body);
            $this->acknowledgeTicketCommand($shop, $metaChatMessage);
        } catch (\Throwable $e) {
            Log::error('WhatsApp ticket command failed', [
                'meta_message_id' => $metaChatMessage->meta_message_id,
                'shop_id'         => $shop->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * The sender is staff when their phone is on an Employee record that is still employed and
     * whose login is active. `employees.phone` is stored as typed - conventionally E.164, but one
     * row carries spaces - so both sides are compared on stripped digits.
     *
     * The user is reached through `getUser()` rather than `employees.user_id`: that column is a
     * partial mirror that was never backfilled, and a quarter of the employees with an active
     * login have it empty.
     */
    protected function staffReporter(Shop $shop, string $digits): ?User
    {
        if ($digits === '') {
            return null;
        }

        // ponytail: `employees.phone` has no index and the digit-stripping scan cannot use one; at
        // the current table size, and only for messages already starting with `/ticket`, that is
        // free. Add a functional index on the normalised expression if the table grows.
        $employee = Employee::where('group_id', $shop->group_id)
            ->whereIn('state', ['working', 'leaving'])
            ->whereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits])
            ->orderByRaw('organisation_id = ? DESC', [$shop->organisation_id])
            ->orderByDesc('id')
            ->first();

        // getUser() applies no ordering, and an employee can carry more than one active login
        // (a mistyped duplicate username), so the oldest is pinned rather than an arbitrary row.
        $reporter = $employee?->users()
            ->wherePivot('status', true)
            ->where('users.status', true)
            ->orderBy('users.id')
            ->first();

        return $reporter;
    }

    /**
     * Returns the text after the `/ticket` prefix, or null when this is not the command.
     * `/ticketing` must not trigger, so the prefix has to end the word.
     */
    protected function ticketCommandBody(string $messageText): ?string
    {
        if (!preg_match('/^\s*\/ticket(?:\s+(.*))?$/is', $messageText, $match)) {
            return null;
        }

        $body = trim($match[1] ?? '');

        return $body === '' ? null : $body;
    }

    protected function acknowledgeTicketCommand(Shop $shop, MetaChatMessage $metaChatMessage): void
    {
        // SendWhatsappReaction needs a ChatAgent and the authenticated user behind it to
        // toggle the reaction; neither exists in a queued webhook, so the tick is posted here.
        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return;
        }

        Http::withToken($accessToken)->post($this->whatsappEndpoint($phoneNumberId.'/messages'), [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/\D/', '', (string) $metaChatMessage->metaChatSession?->phone_number),
            'type'              => 'reaction',
            'reaction'          => [
                'message_id' => $metaChatMessage->meta_message_id,
                'emoji'      => '✅',
            ],
        ]);
    }

    protected function findCustomer(Shop $shop, string $digits): ?Customer
    {
        // ponytail: customers.phone has no index, so try the exact E.164 form first (~97% of rows)
        // and only fall back to the full digit-stripped scan. Add an index on the normalised phone
        // if inbound volume makes the fallback hurt.
        return Customer::where('shop_id', $shop->id)->where('phone', '+'.$digits)->first()
            ?? Customer::where('shop_id', $shop->id)
                ->whereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits])
                ->first();
    }
}
