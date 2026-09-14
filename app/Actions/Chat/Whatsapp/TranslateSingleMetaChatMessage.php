<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp;

use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateSingleMetaChatMessage
{
    use AsAction;

    public function handle(MetaChatMessage $metaChatMessage, int $targetLanguageId): void
    {
        $exists = $metaChatMessage->translations()
            ->where('target_language_id', $targetLanguageId)
            ->exists();

        if ($exists) {
            return;
        }

        TranslateMetaChatMessage::dispatch($metaChatMessage->id, $targetLanguageId);
    }

    public function asController(ActionRequest $request, MetaChatMessage $metaChatMessage): JsonResponse
    {
        $this->handle($metaChatMessage, $request->validated()['target_language_id']);

        $metaChatMessage->load(['translations.targetLanguage', 'originalLanguage']);

        return response()->json([
            'success' => true,
            'message' => 'Message translation processed successfully',
            'data'    => new MetaChatMessageResource($metaChatMessage),
        ]);
    }

    public function rules(): array
    {
        return [
            'target_language_id' => 'required|exists:languages,id',
        ];
    }
}
