<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp;

use App\Models\SysAdmin\User;
use App\Models\Catalogue\Shop;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\MetaChatSession\SetMetaChatMessageReaction;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendWhatsappReaction
{
    use AsAction;
    use WithChatAgentAuthorisation;
    use WithWhatsappCredentials;

    public function authorize(ActionRequest $request): bool
    {
        $shop = $request->route('metaChatMessage')?->metaChatSession?->shop;

        return $shop instanceof Shop && $request->user() instanceof User && $this->userCanActOnChatOnShop($request->user(), $shop);
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * Sends the agent's reaction to WhatsApp so the customer sees it on their own
     * message, then records it locally. Clicking the same emoji again removes it, which
     * Meta expects as a reaction with an empty emoji.
     */
    public function handle(MetaChatMessage $metaChatMessage, ?ChatAgent $agent, string $emoji): array
    {
        $metaChatSession = $metaChatMessage->metaChatSession;

        if (!$metaChatSession->can_send_non_template_message) {
            return [
                'ok'      => false,
                'message' => __('The customer has not messaged in the last 24 hours, so reactions cannot be sent.'),
                'code'    => 422,
            ];
        }

        if (blank($metaChatMessage->meta_message_id)) {
            return [
                'ok'      => false,
                'message' => __('This message cannot be reacted to.'),
                'code'    => 422,
            ];
        }

        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($metaChatSession->shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'ok'      => false,
                'message' => __('WhatsApp is not configured for this shop.'),
                'code'    => 422,
            ];
        }

        $reactorId = Auth::id();
        $isRemoval = $metaChatMessage->reactions()
            ->where('reactor_type', ChatSenderTypeEnum::AGENT->value)
            ->where('reactor_id', $reactorId)
            ->where('emoji', $emoji)
            ->exists();

        if ($this->isWhatsappSendHeldLocally('reaction', [
            'meta_chat_message_id' => $metaChatMessage->id,
            'emoji'                => $emoji,
        ])) {
            return [
                'ok'      => false,
                'message' => __('WhatsApp messages are not sent from a local environment.'),
                'code'    => 422,
            ];
        }

        $response = Http::withToken($accessToken)->post($this->whatsappEndpoint($phoneNumberId.'/messages'), [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            ...$metaChatSession->whatsappRecipient(),
            'type'              => 'reaction',
            'reaction'          => [
                'message_id' => $metaChatMessage->meta_message_id,
                'emoji'      => $isRemoval ? '' : $emoji,
            ],
        ]);

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Failed to send the reaction.'),
                'code'    => 422,
            ];
        }

        $metaChatMessage = SetMetaChatMessageReaction::run(
            $metaChatMessage,
            ChatSenderTypeEnum::AGENT->value,
            $reactorId,
            $isRemoval ? '' : $emoji,
            Arr::get($response->json(), 'messages.0.id')
        );

        return ['ok' => true, 'data' => $metaChatMessage];
    }

    public function asController(MetaChatMessage $metaChatMessage, ActionRequest $request): array
    {
        $agent = Auth::user()?->chatAgent;

        if (!$agent) {
            return [
                'ok'      => false,
                'message' => __('Only agents can react to messages.'),
                'code'    => 403,
            ];
        }

        return $this->handle($metaChatMessage, $agent, $request->validated('emoji'));
    }

    public function jsonResponse(array $result): JsonResponse
    {
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 400);
        }

        return response()->json([
            'success' => true,
            'data'    => MetaChatMessageResource::make($result['data']),
        ]);
    }
}
