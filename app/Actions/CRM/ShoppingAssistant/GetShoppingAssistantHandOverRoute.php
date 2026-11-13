<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\ChatSession\IsChatStaffed;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Models\Chat\ChatAgent;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use App\Services\Gmail\GmailClient;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How a question the assistant cannot answer reaches a person right now:
 *
 * - live_chat: the website has its chat on, it is inside the shop's chat hours (the same hours the
 *   chat window goes by) and an agent of the shop is available. The customer talks to them in the
 *   website's chat window.
 * - email_chat: otherwise, when the shop's mailbox can send and the customer has an email address.
 *   The agent's reply is emailed and the customer's reply comes back into the same chat.
 * - web_chat: otherwise, when the website has its chat on. The reply waits in the chat window, as
 *   the shop has no mailbox to send it from.
 * - null: neither chat nor mailbox, so a person is not offered at all.
 */
class GetShoppingAssistantHandOverRoute
{
    use AsAction;

    /**
     * @return array{route: ?ShoppingAssistantHandOverRouteEnum, in_hours: bool, agent_available: bool}
     */
    public function handle(Website $website, Customer $customer): array
    {
        $shop           = $website->shop;
        $chatIsOn       = self::chatIsOn($website);
        $inHours        = IsChatStaffed::run($shop);
        $agentAvailable = $chatIsOn && $inHours && ChatAgent::findAvailableAgent(null, $shop->id) !== null;

        return [
            'route'           => match (true) {
                $agentAvailable                      => ShoppingAssistantHandOverRouteEnum::LIVE_CHAT,
                self::canEmail($website, $customer) => ShoppingAssistantHandOverRouteEnum::EMAIL_CHAT,
                $chatIsOn                            => ShoppingAssistantHandOverRouteEnum::WEB_CHAT,
                default                              => null,
            },
            'in_hours'        => $inHours,
            'agent_available' => $agentAvailable,
        ];
    }

    /**
     * Whether a person can be offered at all, without asking who is online: what the prompt and the
     * Talk to a person link go by.
     */
    public static function isOffered(Website $website, Customer $customer): bool
    {
        return self::chatIsOn($website) || self::canEmail($website, $customer);
    }

    private static function chatIsOn(Website $website): bool
    {
        return (bool) data_get($website->settings, 'enable_chat');
    }

    private static function canEmail(Website $website, Customer $customer): bool
    {
        return filled($customer->email) && GmailClient::forShop($website->shop) !== null;
    }
}
