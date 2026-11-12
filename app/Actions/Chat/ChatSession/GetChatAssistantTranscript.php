<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatSessionReadAuthorisation;
use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantSettings;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantAnswerKindEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverRouteEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\Chat\ChatSession;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantHandOver;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the AI shopping assistant said before it passed a chat to a person, shown at the top of
 * that chat. A chat is "from the assistant" when chat_sessions.shopping_assistant_conversation_id
 * is set; the transcript is read from the assistant's own tables and never copied into
 * chat_messages, because the chat reports count an ai message there as the first reply.
 */
class GetChatAssistantTranscript
{
    use AsAction;
    use WithChatSessionReadAuthorisation;

    /**
     * The mark on a chat in the inbox list: null for an ordinary chat, at no query. The latest
     * hand-over of the chat says why and by which route.
     *
     * @return array{reason: string, reason_label: string, route: string, route_label: string, name: string}|null
     */
    public static function forList(ChatSession $chatSession): ?array
    {
        if (!$chatSession->shopping_assistant_conversation_id) {
            return null;
        }

        $handOver = ShoppingAssistantHandOver::query()
            ->where('chat_session_id', $chatSession->id)
            ->orderByDesc('id')
            ->first();

        $website = $chatSession->shop_id ? $chatSession->shop?->website : null;

        return [
            'reason'       => $handOver?->reason->value ?? '',
            'reason_label' => $handOver ? (ShoppingAssistantHandOverReasonEnum::labels()[$handOver->reason->value] ?? $handOver->reason->value) : '',
            'route'        => $handOver?->route->value ?? '',
            'route_label'  => $handOver ? (ShoppingAssistantHandOverRouteEnum::labels()[$handOver->route->value] ?? $handOver->route->value) : '',
            'name'         => self::assistantName($website),
        ];
    }

    public static function assistantName(?Website $website): string
    {
        return $website instanceof Website
            ? GetShoppingAssistantSettings::name($website)
            : (string) config('services.shopping_assistant.name');
    }

    public function asController(ChatSession $chatSession): array
    {
        abort_unless($chatSession->shopping_assistant_conversation_id, 404);

        return $this->handle($chatSession);
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(ChatSession $chatSession): array
    {
        $conversation = ShoppingAssistantConversation::query()->findOrFail($chatSession->shopping_assistant_conversation_id);

        $messages = ShoppingAssistantMessage::query()
            ->where('shopping_assistant_conversation_id', $conversation->id)
            ->orderBy('id')
            ->get();

        $productsByMessage = DB::table('shopping_assistant_events')
            ->join('products', 'products.id', '=', 'shopping_assistant_events.product_id')
            ->where('shopping_assistant_events.shopping_assistant_conversation_id', $conversation->id)
            ->where('shopping_assistant_events.type', ShoppingAssistantEventTypeEnum::SHOWN->value)
            ->whereNotNull('shopping_assistant_events.shopping_assistant_message_id')
            ->orderBy('shopping_assistant_events.position')
            ->orderBy('shopping_assistant_events.id')
            ->get(['shopping_assistant_events.shopping_assistant_message_id as message_id', 'products.code', 'products.name'])
            ->groupBy('message_id');

        $handOvers = ShoppingAssistantHandOver::query()
            ->where('chat_session_id', $chatSession->id)
            ->orderBy('id')
            ->get();

        $user = request()->user();

        return [
            'assistant_name' => self::assistantName($conversation->website),
            'conversation'   => $conversation->ulid,
            'hand_overs'     => $handOvers->map(fn (ShoppingAssistantHandOver $handOver) => [
                'reason'       => $handOver->reason->value,
                'reason_label' => ShoppingAssistantHandOverReasonEnum::labels()[$handOver->reason->value] ?? $handOver->reason->value,
                'route'        => $handOver->route->value,
                'route_label'  => ShoppingAssistantHandOverRouteEnum::labels()[$handOver->route->value] ?? $handOver->route->value,
                'in_hours'     => $handOver->in_hours,
                'question'     => $handOver->question,
                'created_at'   => $handOver->created_at,
            ])->values()->all(),
            'can_rate'       => $user && $chatSession->shop && $this->userCanActOnChatOnShop($user, $chatSession->shop),
            'messages'       => $messages->map(fn (ShoppingAssistantMessage $message) => [
                'id'                => $message->id,
                'role'              => $message->role->value,
                'body'              => $message->body,
                'created_at'        => $message->created_at,
                'answer_kind'       => $message->answer_kind?->value,
                'answer_kind_label' => $message->answer_kind ? (ShoppingAssistantAnswerKindEnum::labels()[$message->answer_kind->value] ?? $message->answer_kind->value) : null,
                'sources'           => collect($message->sources ?? [])
                    ->map(fn (array $source) => ['title' => $source['title'] ?? '', 'url' => $source['url'] ?? null])
                    ->values()
                    ->all(),
                'products'          => $message->role === ShoppingAssistantMessageRoleEnum::ASSISTANT
                    ? collect($productsByMessage->get($message->id, []))->map(fn ($product) => ['code' => $product->code, 'name' => $product->name])->values()->all()
                    : [],
                'hand_over_offer'   => $message->hand_over_offer?->value,
                'handed_over'       => in_array('handed_over', $message->flags ?? [], true),
                'staff_rating'      => $message->staff_rating,
            ])->values()->all(),
        ];
    }
}
