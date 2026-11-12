<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSession;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff mark an answer the AI shopping assistant gave before handing a chat over as wrong, or take
 * the mark back (rating null). Only an assistant message of the conversation the chat came from
 * can be rated, and only by someone who may act on chat on the chat's shop.
 */
class RateChatAssistantAnswer
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const string WRONG = 'wrong';

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();
        $shop = $request->route('chatSession')?->shop;

        return $user instanceof User && $shop instanceof Shop && $this->userCanActOnChatOnShop($user, $shop);
    }

    public function rules(): array
    {
        return [
            'rating' => ['present', 'nullable', 'string', 'in:'.self::WRONG],
        ];
    }

    public function handle(ShoppingAssistantMessage $message, ?string $rating, User $user): ShoppingAssistantMessage
    {
        $message->update([
            'staff_rating'           => $rating,
            'staff_rated_by_user_id' => $rating ? $user->id : null,
            'staff_rated_at'         => $rating ? now() : null,
        ]);

        return $message;
    }

    public function asController(ChatSession $chatSession, ShoppingAssistantMessage $message, ActionRequest $request): array
    {
        abort_unless(
            $chatSession->shopping_assistant_conversation_id
            && $message->shopping_assistant_conversation_id === $chatSession->shopping_assistant_conversation_id
            && $message->role === ShoppingAssistantMessageRoleEnum::ASSISTANT,
            404
        );

        $message = $this->handle($message, $request->validated('rating'), $request->user());

        return ['staff_rating' => $message->staff_rating];
    }
}
