<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Events\BroadcastMetaChatListEvent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RestoreMetaChatSession
{
    use AsAction;

    /**
     * Brings a trashed thread back. Meta sessions never store `waiting` — it is derived
     * from the absence of an active assignment — so restoring only has to undo the
     * soft delete for the thread to land in the right tab.
     *
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession): MetaChatSession
    {
        return DB::transaction(function () use ($metaChatSession) {
            $metaChatSession->restore();

            BroadcastMetaChatListEvent::dispatch(null, $metaChatSession);

            return $metaChatSession->fresh();
        });
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        if (!Auth::user()?->chatAgent) {
            return response()->json([
                'success' => false,
                'message' => __('Only authenticated agents can restore chats'),
            ], 403);
        }

        try {
            $metaChatSession = $this->handle($metaChatSession);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('Chat restored'),
            'data'    => ['session_ulid' => $metaChatSession->ulid],
        ]);
    }
}
