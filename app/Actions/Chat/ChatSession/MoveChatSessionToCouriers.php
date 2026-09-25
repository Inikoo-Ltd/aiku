<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\UpdateGroupChatCarrierDomains;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A courier we did not know yet: its domain joins the group's courier list, and the open
 * conversations from it, this one included, go to the Couriers folder.
 */
class MoveChatSessionToCouriers
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * @return int the conversations filed
     */
    public function handle(ChatSession $chatSession): int
    {
        $domain = self::senderDomain($chatSession);
        $group  = $chatSession->shop->group;

        UpdateGroupChatCarrierDomains::make()->handle($group, [
            'domains' => implode("\n", [...ProcessInboundEmail::carrierDomains($group), $domain]),
        ]);

        $sessions = ChatSession::query()
            ->whereIn('shop_id', Shop::where('group_id', $group->id)->select('id'))
            ->where('channel', ChatChannelEnum::EMAIL->value)
            ->whereNull('web_user_id')
            ->where('is_carrier', false)
            ->where(fn ($query) => $query->where('id', $chatSession->id)->orWhere('status', '!=', ChatSessionStatusEnum::CLOSED->value))
            ->where(function ($query) use ($domain) {
                $senderDomain = "lower(split_part(metadata->>'email_from', '@', 2))";
                $query->whereRaw("$senderDomain = ?", [$domain])->orWhereRaw("$senderDomain like ?", ['%.'.$domain]);
            })
            ->get();

        DB::transaction(fn () => ChatSession::whereIn('id', $sessions->pluck('id'))->update(['is_carrier' => true]));

        $sessions->each(fn (ChatSession $session) => BroadcastChatListEvent::dispatch(null, $session));

        return $sessions->count();
    }

    public static function senderDomain(ChatSession $chatSession): string
    {
        $address = (string) (data_get($chatSession->metadata, 'email_from') ?: data_get($chatSession->metadata, 'email'));

        return mb_strtolower((string) substr(strrchr($address, '@') ?: '', 1));
    }

    /**
     * The list is the whole group's, so only a supervisor adds to it. Free mail domains are
     * refused: one gmail.com courier would send every gmail customer who is not signed in there.
     */
    public static function canBeMoved(ChatSession $chatSession): bool
    {
        $domain = self::senderDomain($chatSession);

        return $chatSession->channel === ChatChannelEnum::EMAIL
            && !$chatSession->web_user_id
            && !$chatSession->is_carrier
            && $domain !== ''
            && !in_array($domain, SuggestChatSessionCustomer::FREE_MAIL_DOMAINS, true);
    }

    public function userMayMove(?User $user, ChatSession $chatSession): bool
    {
        return $user instanceof User
            && self::canBeMoved($chatSession)
            && $chatSession->shop instanceof Shop
            && $this->userSupervisesChatOnShop($user, $chatSession->shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        if (!$chatSession->shop instanceof Shop || !$this->userSupervisesChatOnShop($request->user(), $chatSession->shop)) {
            abort(403, __('Only a chat supervisor can add a courier.'));
        }

        if (!self::canBeMoved($chatSession)) {
            throw ValidationException::withMessages([
                'chat_session' => __('Only an email from a sender who is not a customer, and not from a free mail provider, can be moved to Couriers.'),
            ]);
        }

        $filed = $this->handle($chatSession);

        return response()->json([
            'success' => true,
            'message' => __(':domain added to the couriers, :count conversations moved to Couriers', [
                'domain' => self::senderDomain($chatSession),
                'count'  => $filed,
            ]),
            'data'    => ['session_ulid' => $chatSession->ulid, 'is_carrier' => true, 'filed' => $filed],
        ]);
    }
}
