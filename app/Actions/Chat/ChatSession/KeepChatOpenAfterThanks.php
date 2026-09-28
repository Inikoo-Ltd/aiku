<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An agent who starts typing, or clicks "Keep open", stops the 👍 and close for good.
 */
class KeepChatOpenAfterThanks
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession|MetaChatSession $chatSession): void
    {
        CloseChatAfterThanks::cancel($chatSession);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWhatsapp(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->respond($metaChatSession);
    }

    private function respond(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $this->handle($chatSession);

        return response()->json(['success' => true]);
    }
}
