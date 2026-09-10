<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Models\Chat\MetaChatAssignment;
use App\Models\Chat\MetaChatEvent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteMetaChatSessionPermanently
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession): void
    {
        DB::transaction(function () use ($metaChatSession) {
            MetaChatMessage::withTrashed()
                ->where('meta_chat_session_id', $metaChatSession->id)
                ->get()
                ->each(function (MetaChatMessage $metaChatMessage) {
                    $metaChatMessage->attachment?->delete();
                    $metaChatMessage->forceDelete();
                });

            MetaChatAssignment::where('meta_chat_session_id', $metaChatSession->id)->delete();
            MetaChatEvent::where('meta_chat_session_id', $metaChatSession->id)->delete();

            $metaChatSession->forceDelete();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        if (!Auth::user()?->chatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can delete chats'),
            ], 403);
        }

        $ulid = $metaChatSession->ulid;

        try {
            $this->handle($metaChatSession);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat permanently deleted'),
            'data'    => ['session_ulid' => $ulid],
        ]);
    }
}
