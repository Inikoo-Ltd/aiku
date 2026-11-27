<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Actions\Comms\Email\SendChatNotificationToCustomer;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer who was told a person is coming and then hears nothing is worse off than one told the
 * truth: a hand-over to the live chat ("your chat with them is opening now") that no agent picks up.
 * After WAIT_MINUTES with no reply the customer is told, once, in the conversation and by email where
 * the shop's chat notification email is on: outside chat hours when the team is back, inside them
 * that nobody is free right now. The chat stays in the unclaimed queue; nothing is closed here.
 */
class TellShoppingAssistantCustomersTeamIsAway
{
    use AsAction;

    public const int WAIT_MINUTES = 10;

    public const string FLAG = 'team_away';

    public string $commandSignature = 'shopping-assistant:tell-waiting-customers';

    public string $commandDescription = 'Tell the customers nobody answered after a hand-over when the team is back';

    public function handle(): int
    {
        $told = 0;

        ShoppingAssistantHandOver::where('route', ShoppingAssistantHandOverRouteEnum::LIVE_CHAT)
            ->whereBetween('created_at', [now()->subDay(), now()->subMinutes(self::WAIT_MINUTES)])
            ->whereHas('chatSession', fn ($query) => $query->where('status', ChatSessionStatusEnum::WAITING)->whereDoesntHave('messages', fn ($messages) => $messages->where('sender_type', ChatSenderTypeEnum::AGENT)))
            ->with('conversation.customer.shop', 'conversation.website')
            ->get()
            ->each(function (ShoppingAssistantHandOver $handOver) use (&$told) {
                $conversation = $handOver->conversation;
                if (!$conversation?->customer || $conversation->wiped_at || $conversation->messages()->whereJsonContains('flags', self::FLAG)->where('created_at', '>=', $handOver->created_at)->exists()) {
                    return;
                }

                $this->tell($conversation);
                $told++;
            });

        return $told;
    }

    /**
     * The sentences are the ones a hand-over already uses out of hours, so they exist in every
     * storefront language. A reply to a hand-over waits in the website chat.
     */
    private function tell(ShoppingAssistantConversation $conversation): void
    {
        $locale   = $conversation->language_code;
        $customer = $conversation->customer;
        $shop     = $customer->shop;
        $hours    = IsWithinWorkingHours::make()->chatHours();
        $opening  = $hours->handle($shop, now()) ? null : $hours->nextOpening($shop, now());
        $replace  = $opening ? [
            'day'  => $opening['opens']->copy()->locale($locale)->isoFormat('dddd'),
            'time' => $shop->organisation->formatClockTime($opening['opens']),
        ] : [];

        $body = $opening !== null
            ? __('Our team is back on :day at :time and will reply in the chat on this website.', $replace, $locale)
            : __('Nobody from our team is free right now. They will reply in the chat on this website.', [], $locale);

        $conversation->messages()->create([
            'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
            'body'  => $body,
            'model' => 'team',
            'flags' => [self::FLAG],
        ]);
        $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

        $emailIsOn = $shop->outboxes()
            ->where('code', OutboxCodeEnum::CHAT_NOTIFICATION_TO_CUSTOMER->value)
            ->where('state', OutboxStateEnum::ACTIVE->value)
            ->exists();

        if ($emailIsOn && $customer->email) {
            SendChatNotificationToCustomer::dispatch($customer, FollowUpShoppingAssistantRestockQuestion::emailData($conversation, $body));
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $told = $this->handle();
        $command->info('Told '.$told.' customers waiting in a hand-over');

        return 0;
    }
}
