<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jev compares each suggestion staff answered after with what they really sent, shown or
 * shadow, and keeps the score on the suggestion (facts.near_match): close is a score of 2 or
 * more of 3. Run nightly for the day before; the table says, per shop, how often suggestions
 * were close, which is what decides where they are shown.
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
            ->where('facts->mode', DraftChatReply::SUGGESTION)
            ->whereNull('facts->near_match')
            ->whereNotNull('reply_message_id')
            ->whereBetween('decided_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->each(function (ChatAiDraft $draft) use (&$byShop) {
                $message = $draft->chat_session_id ? ChatMessage::class : MetaChatMessage::class;
                $staff   = (string) $message::find($draft->reply_message_id)?->message_text;
                $match   = $staff === '' ? null : JudgeChatSuggestion::make()->nearMatch((string) $message::find($draft->trigger_message_id)?->message_text, $staff, $draft->text);

                if (!$match) {
                    return;
                }

                $draft->update(['facts' => $draft->facts + ['near_match' => $match]]);

                $shop                      = $draft->shop->slug.(($draft->facts['shadow'] ?? false) ? ' (shadow)' : '');
                $byShop[$shop]['scored']   = ($byShop[$shop]['scored'] ?? 0) + 1;
                $byShop[$shop]['close']    = ($byShop[$shop]['close'] ?? 0) + (int) ($match['score'] >= 2);
            });

        ksort($byShop);

        return $byShop;
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
