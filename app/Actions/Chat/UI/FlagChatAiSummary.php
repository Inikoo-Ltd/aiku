<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\SummarizeLongEmail;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff saying an AI summary, of a long email or of a whole chat, was wrong, and why: the
 * reason is what the summaries are corrected from. Each flag keeps the summary it was about,
 * so a chat summarised again later still shows what was wrong with the old one.
 */
class FlagChatAiSummary
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const string KEY = 'ai_summary_flags';

    public function handle(ChatMessage|ChatSession|MetaChatSession $model, int $userId, string $reason): ChatMessage|ChatSession|MetaChatSession
    {
        $metadata = $model->metadata ?? [];

        $metadata[self::KEY][] = [
            'summary'    => self::summaryOf($model),
            'reason'     => $reason,
            'user_id'    => $userId,
            'flagged_at' => now()->toISOString(),
        ];

        $model->update(['metadata' => $metadata]);

        return $model;
    }

    public static function summaryOf(ChatMessage|ChatSession|MetaChatSession $model): ?string
    {
        return $model instanceof ChatMessage
            ? Arr::get($model->metadata, SummarizeLongEmail::KEY)
            : Arr::get($model->metadata, 'ai_summary.summary');
    }

    public static function isFlagged(ChatMessage|ChatSession|MetaChatSession $model): bool
    {
        $summary = self::summaryOf($model);

        return $summary !== null && Arr::get(Arr::last(Arr::get($model->metadata, self::KEY, [])), 'summary') === $summary;
    }

    public function asController(ChatMessage $chatMessage, Request $request): JsonResponse
    {
        return $this->flag($chatMessage, $chatMessage->chatSession, $request);
    }

    public function inChatSession(ChatSession $chatSession, Request $request): JsonResponse
    {
        return $this->flag($chatSession, $chatSession, $request);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession, Request $request): JsonResponse
    {
        return $this->flag($metaChatSession, $metaChatSession, $request);
    }

    private function flag(ChatMessage|ChatSession|MetaChatSession $model, ChatSession|MetaChatSession|null $chatSession, Request $request): JsonResponse
    {
        abort_unless(self::summaryOf($model) && $this->mayFlag($request->user(), $chatSession), 404);
        abort_if(self::isFlagged($model), 422, __('This summary is already marked as wrong'));

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        DB::transaction(function () use ($model, $request, $reason) {
            $locked = $model::query()->lockForUpdate()->findOrFail($model->id);
            abort_if(self::isFlagged($locked), 422, __('This summary is already marked as wrong'));

            $this->handle($locked, $request->user()->id, $reason);
        });

        return response()->json(['success' => true]);
    }

    private function mayFlag(User $user, ChatSession|MetaChatSession|null $chatSession): bool
    {
        $shop = $chatSession?->shop;

        return $shop
            && $shop->group_id === $user->group_id
            && ($user->hasGroupAccess() || $this->userCanActOnChatOnShop($user, $shop));
    }
}
