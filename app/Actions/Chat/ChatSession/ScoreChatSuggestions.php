<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiDraftChangeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jev compares each suggestion and assistant draft staff answered after with what they really sent, shown or
 * shadow, and keeps the score on the suggestion (facts.near_match): close is a score of 2 or
 * more of 3. Run nightly for the day before; the table says, per shop, how often suggestions
 * were close, which is what decides where they are shown. A discarded draft is scored against the
 * agent's next message, only when it is the agent who discarded it answering before the customer
 * wrote again. A draft staff ignored or discarded and then retyped (score 2 or more) is marked
 * time_lost (INI-400): a right draft written again by hand. A draft that still had a [[gap]]
 * could not be sent as it was, so it is never time lost.
 */
class ScoreChatSuggestions
{
    use AsAction;

    public string $commandSignature = 'chat:score-suggestions {--date= : Day the staff replied (default yesterday, UTC)}';

    /**
     * @return array<string, array{scored: int, close: int}>
     */
    public function handle(Carbon $day): array
    {
        $byShop = [];

        ChatAiDraft::with('shop')
            ->whereIn('facts->mode', [DraftChatReply::SUGGESTION, DraftChatReply::ASSISTANT])
            ->whereNull('facts->near_match')
            ->where(fn ($query) => $query->whereNotNull('reply_message_id')->orWhere('change_class', ChatAiDraftChangeEnum::DISCARDED->value))
            ->whereBetween('decided_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->each(function (ChatAiDraft $draft) use (&$byShop) {
                $message = $draft->chat_session_id ? ChatMessage::class : MetaChatMessage::class;
                $reply   = $draft->reply_message_id ? $message::find($draft->reply_message_id) : $this->nextAgentMessage($draft, $message);
                $staff   = (string) $reply?->message_text;
                $match   = $staff === '' ? null : JudgeChatSuggestion::make()->nearMatch((string) $message::find($draft->trigger_message_id)?->message_text, $staff, $draft->text);

                if (!$match) {
                    return;
                }

                $timeLost = in_array($draft->change_class, [ChatAiDraftChangeEnum::OWN_REPLY, ChatAiDraftChangeEnum::DISCARDED], true)
                    && ($draft->facts['mode'] ?? null) === DraftChatReply::ASSISTANT && !($draft->facts['shadow'] ?? false)
                    ? $match['score'] >= 2 && !str_contains($draft->text, '[[')
                    : null;
                $draft->update(array_filter([
                    'facts'             => $draft->facts + ['near_match' => $match],
                    'time_lost'         => $timeLost,
                    'time_lost_seconds' => $timeLost ? Arr::get($reply?->metadata ?? [], 'writing_seconds') : null,
                ], fn ($value) => $value !== null));

                $shop                      = $draft->shop->slug.(($draft->facts['shadow'] ?? false) ? ' (shadow)' : '').(($draft->facts['mode'] ?? null) === DraftChatReply::ASSISTANT ? ' (assistant)' : '');
                $byShop[$shop]['scored']   = ($byShop[$shop]['scored'] ?? 0) + 1;
                $byShop[$shop]['close']    = ($byShop[$shop]['close'] ?? 0) + (int) ($match['score'] >= 2);
            });

        ksort($byShop);

        return $byShop;
    }

    /**
     * A discarded draft has no reply of its own: what the agent sent next in the chat is what they
     * wrote instead of it.
     *
     * @param  class-string<ChatMessage|MetaChatMessage>  $message
     */
    private function nextAgentMessage(ChatAiDraft $draft, string $message): ChatMessage|MetaChatMessage|null
    {
        if (!$draft->trigger_message_id) {
            return null;
        }

        $next = $message::where($draft->chat_session_id ? 'chat_session_id' : 'meta_chat_session_id', $draft->chat_session_id ?? $draft->meta_chat_session_id)
            ->where('id', '>', $draft->trigger_message_id)
            ->where('created_at', '>=', $draft->created_at)
            ->whereIn('sender_type', [ChatSenderTypeEnum::AGENT, ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->orderBy('id')
            ->first();

        if ($next?->sender_type !== ChatSenderTypeEnum::AGENT) {
            return null;
        }

        return !$draft->decided_by_user_id || ChatAgent::whereKey($next->sender_id)->value('user_id') === $draft->decided_by_user_id ? $next : null;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $byShop = $this->handle(Carbon::parse($command->option('date') ?? now('UTC')->subDay()->toDateString(), 'UTC'));

        $command->table(['Shop', 'Scored', 'Close', 'Close %'], collect($byShop)->map(fn (array $row, string $shop) => [
            $shop, $row['scored'], $row['close'], round(100 * $row['close'] / $row['scored']).'%',
        ])->values()->all());

        return 0;
    }
}
