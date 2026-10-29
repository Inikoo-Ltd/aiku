<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\ChatSession\ClassifyChatTurn;
use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\Helpers\Language;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Customer service tools start from the Chat ID staff copy out of the chat's Overview panel: the
 * conversation names the shop, the customer and so the orders that can be touched, and nothing
 * outside that customer is reachable from it. Writing is gated by an enrolment on the user's
 * account, on top of the chat and order permissions the same person needs in Aiku. The tools stay
 * registered so an assistant that is not enrolled gets a clear refusal instead of an unknown tool.
 */
abstract class AikuCustomerServiceTool extends Tool
{
    use WithChatAgentAuthorisation;
    use WithMcpPermissions;

    protected ?ChatAgent $chatAgent = null;

    protected function notEnrolled(Request $request): ?Response
    {
        if ($request->user()?->can_use_mcp_customer_service) {
            return null;
        }

        return Response::error('Serving customers is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page. Nothing was changed.');
    }

    /**
     * A Chat ID names a website or email chat, else a WhatsApp chat.
     *
     * @return array{0: ChatSession|MetaChatSession|null, 1: Response|null}
     */
    protected function chatSessionFor(Request $request, bool $toActOnIt): array
    {
        /** @var User $user */
        $user = $request->user();
        setPermissionsTeamId($user->group_id);

        if (!Auth::user()) {
            Auth::setUser($user);
        }

        $ulid        = strtoupper(trim((string) $request->string('chat_id')));
        $chatSession = ChatSession::where('ulid', $ulid)->first() ?? MetaChatSession::where('ulid', $ulid)->first();

        if (!$chatSession || !$chatSession->shop || !$this->userCanViewChatOnShop($user, $chatSession->shop)) {
            return [null, Response::error('No chat with that ID that this user can open. The Chat ID is copied from the Overview panel of the chat, e.g. 01M3YHCN5YPXEDJN1MYJG1DGEF.')];
        }

        if ($toActOnIt) {
            $this->chatAgent = $this->getAuthorisedChatAgent($chatSession);

            if (!$this->chatAgent) {
                return [null, Response::error('This user can read the chats of '.$chatSession->shop->name.' but does not work them. Nothing was changed.')];
            }
        }

        return [$chatSession, null];
    }

    /**
     * Named by Aiku, never left to the assistant: the language of what the customer wrote.
     */
    protected function replyLanguage(ChatSession|MetaChatSession $chatSession): ?Language
    {
        $trigger = $chatSession->messages()->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])->latest('id')->first();

        if (!$trigger) {
            return $chatSession->language ?? $chatSession->shop->language;
        }

        return DraftChatReply::replyLanguage($chatSession, $trigger, ClassifyChatTurn::customerWrote($chatSession));
    }

    protected function customerOf(ChatSession|MetaChatSession $chatSession): ?Customer
    {
        if ($chatSession instanceof MetaChatSession) {
            return DraftChatReply::knownCustomer($chatSession);
        }

        return $chatSession->webUser?->customer;
    }

    /**
     * @return array{0: Order|null, 1: Response|null}
     */
    protected function orderOf(ChatSession|MetaChatSession $chatSession, string $reference): array
    {
        $customer = $this->customerOf($chatSession);

        if (!$customer) {
            return [null, Response::error('This chat is not linked to a customer yet, so it has no orders. Link the customer first.')];
        }

        $order = $customer->orders()->whereRaw('lower(reference) = ?', [strtolower(trim($reference))])->first();

        if (!$order) {
            $references = $customer->orders()->latest('id')->limit(10)->pluck('reference')->implode(', ');

            return [null, Response::error("'{$reference}' is not an order of {$customer->name}. Their latest orders: ".($references ?: 'none').'.')];
        }

        return [$order, null];
    }

    protected function cannotOnShop(Request $request, ChatSession|MetaChatSession $chatSession, ShopPermissionsEnum $permission, string $what): ?Response
    {
        if ($this->userCan($request, ShopPermissionsEnum::getPermissionName($permission->value, $chatSession->shop))) {
            return null;
        }

        return Response::error("This user is not allowed to {$what} in {$chatSession->shop->name}. Nothing was changed.");
    }

    /**
     * On WhatsApp the assistant only suggests: anything that would reach the customer, change an
     * order or settle a claim is left to staff in Aiku.
     */
    protected function notOnWhatsapp(string $what): Response
    {
        return Response::error("{$what} is not available on WhatsApp chats: the assistant only leaves drafts there. Nothing was changed.");
    }

    /**
     * WhatsApp takes free text only within 24 hours of the customer's last message; after that only
     * an approved template reaches them.
     *
     * @return array{whatsapp_window_closes_at: string|null, whatsapp_window_minutes_left: int}
     */
    protected function whatsappWindow(MetaChatSession $chatSession): array
    {
        $closesAt = $chatSession->whatsapp_window_closes_at;

        return [
            'whatsapp_window_closes_at'    => $closesAt?->toIso8601String(),
            'whatsapp_window_minutes_left' => $closesAt ? max(0, (int) floor(now()->diffInSeconds($closesAt, false) / 60)) : 0,
        ];
    }

    protected function askToConfirm(array $preview): Response
    {
        return Response::json([
            'preview'   => $preview,
            'next_step' => 'Nothing was changed. Show this to the user and call again with confirm=true only after they say yes in their own words.',
        ]);
    }
}
