<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\SysAdmin\User;
use App\Models\Catalogue\Shop;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateSingleMessage
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if (!$request->user() instanceof User) {
            return false;
        }

        $shop = $request->route('chatMessage')?->chatSession?->shop;

        return $shop instanceof Shop && $request->user() instanceof User && $this->userCanActOnChatOnShop($request->user(), $shop);
    }

    public function handle(ChatMessage $chatMessage, int $targetLanguageId): void
    {
        $exists = $chatMessage->translations()
            ->where('target_language_id', $targetLanguageId)
            ->exists();

        if ($exists) {
            return;
        }

        TranslateChatMessage::run($chatMessage->id, $targetLanguageId, 'translate-single');
    }

    public function asController(ActionRequest $request, ChatMessage $chatMessage): JsonResponse
    {
        $validated = $request->validated();

        $this->handle($chatMessage, $validated['target_language_id']);

        $chatMessage->load(['translations.targetLanguage', 'originalLanguage']);

        return response()->json([
            'success' => true,
            'message' => 'Message translation processed successfully',
            'data'    => new ChatMessageResource($chatMessage)
        ]);
    }

    public function rules(): array
    {
        return [
            'target_language_id' => 'required|exists:languages,id',
        ];
    }
}
