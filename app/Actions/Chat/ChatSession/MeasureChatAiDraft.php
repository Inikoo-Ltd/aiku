<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiDraftChangeEnum;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What staff did with a draft, measured from the texts: how much of it they kept and how much
 * they changed, how long after the customer wrote the draft arrived, and how long after the
 * message was opened they replied. Kept beside the status, which stays as staff see it; these
 * are what decide, per shop and intent, whether drafts could ever go out alone. Run when a
 * draft is decided and nightly for any left unmeasured, which is also the backfill.
 */
class MeasureChatAiDraft
{
    use AsAction;

    public string $commandSignature = 'chat:measure-drafts {--days=90 : How far back to look for drafts not measured yet}';

    private const float KEPT_ALL = 0.95;

    private const float KEPT_NEARLY_ALL = 0.9;

    private const float KEPT_MOST = 0.75;

    private const float KEPT_SOME = 0.4;

    private const int GREETING_AND_NAME_WORDS = 12;

    public function handle(ChatAiDraft $draft): ChatAiDraft
    {
        $message = $draft->chat_session_id ? ChatMessage::class : MetaChatMessage::class;
        $trigger = $draft->trigger_message_id ? $message::find($draft->trigger_message_id) : null;
        $reply   = $draft->reply_message_id ? $message::find($draft->reply_message_id) : null;

        $measures = [
            'lag_seconds' => $trigger ? (int) max(0, $trigger->created_at->diffInSeconds($draft->created_at)) : null,
        ];

        if ($draft->status === ChatAiDraftStatusEnum::DISCARDED) {
            $measures['change_class'] = ChatAiDraftChangeEnum::DISCARDED;
        } elseif ($reply && in_array($draft->status, [ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED, ChatAiDraftStatusEnum::SUPERSEDED], true)) {
            $replyText = (string) $reply->message_text;

            $measures += [
                'words_kept'            => round(SettleChatAiDraft::draftWordsKept($draft->text, $replyText), 3),
                'change_class'          => self::changeClass($draft->text, $replyText, $draft->status),
                'open_to_reply_seconds' => $trigger?->read_at && $reply->created_at->gte($trigger->read_at) ? (int) $trigger->read_at->diffInSeconds($reply->created_at) : null,
            ];
        }

        $draft->update($measures);

        return $draft;
    }

    /**
     * A greeting or a signature around the whole draft is cosmetic; a draft whose [[gaps]] staff
     * filled is not, whatever else they kept, since the AI did not know what went there.
     */
    public static function changeClass(string $draft, string $reply, ChatAiDraftStatusEnum $status): ChatAiDraftChangeEnum
    {
        $kept = SettleChatAiDraft::draftWordsKept($draft, $reply);

        return match (true) {
            SettleChatAiDraft::normalised($draft) === SettleChatAiDraft::normalised($reply) => ChatAiDraftChangeEnum::AS_IS,
            $status === ChatAiDraftStatusEnum::SUPERSEDED                                     => ChatAiDraftChangeEnum::OWN_REPLY,
            preg_match(DraftChatReply::GAP, $draft) === 1 && $kept >= self::KEPT_NEARLY_ALL   => ChatAiDraftChangeEnum::GAPS_FILLED,
            $kept >= self::KEPT_ALL
                && SettleChatAiDraft::wordCount($reply) - SettleChatAiDraft::wordCount($draft) <= self::GREETING_AND_NAME_WORDS => ChatAiDraftChangeEnum::COSMETIC,
            $kept >= self::KEPT_MOST                                                          => ChatAiDraftChangeEnum::SMALL,
            $kept >= self::KEPT_SOME                                                          => ChatAiDraftChangeEnum::BIG,
            default                                                                           => ChatAiDraftChangeEnum::REWRITTEN,
        };
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $measured = 0;
        ChatAiDraft::where('created_at', '>=', now()->subDays((int) $command->option('days')))
            ->where(fn ($unmeasured) => $unmeasured->where(fn ($noLag) => $noLag->whereNull('lag_seconds')->whereNotNull('trigger_message_id'))
                ->orWhere(fn ($decided) => $decided->whereNull('change_class')
                    ->where(fn ($status) => $status->whereIn('status', [ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED, ChatAiDraftStatusEnum::DISCARDED])
                        ->orWhere(fn ($ownReply) => $ownReply->where('status', ChatAiDraftStatusEnum::SUPERSEDED)->whereNotNull('reply_message_id')))))
            ->chunkById(200, function ($drafts) use (&$measured) {
                foreach ($drafts as $draft) {
                    $this->handle($draft);
                    $measured++;
                }
            });

        $command->info("Measured $measured drafts.");

        return 0;
    }
}
