<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\AskJev;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAiReactionEnum;
use App\Enums\CRM\Livechat\ChatAiRiskEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A second opinion on each assistant draft, by Jev and not by the model that wrote it: what the
 * customer asked for, what the draft does, whether it answers everything, and later how the
 * customer took the reply that came from it. Where Jev and the routine disagree, that kind of
 * draft is never sent without a person. Run nightly, which is also the backfill.
 */
class JudgeChatAiDraft
{
    use AsAction;

    public string $commandSignature = 'chat:judge-drafts {--days=90 : How far back to look for assistant drafts not judged yet} {--limit=2000 : At most this many questions to Jev}';

    private const string CONTEXT = 'Customer service of a wholesale giftware supplier.';

    /**
     * @return array<string, mixed>|null what Jev read, or null when it could not be asked
     */
    public function handle(ChatAiDraft $draft): ?array
    {
        $customerWrote = self::text(self::message($draft, $draft->trigger_message_id));
        if ($customerWrote === '' || trim($draft->text) === '') {
            $draft->update(['jev_check' => ['unread' => 'no text to read']]);

            return null;
        }

        $answers = AskJev::make()->handle(['customer_wrote' => mb_substr($customerWrote, 0, 4000), 'draft' => mb_substr($draft->text, 0, 3000)], [
            'intent'      => self::choice('"customer_wrote" is a customer message. What does it ask for?', ChatIntentEnum::definitions()),
            'risk'        => self::choice('"draft" is the reply drafted for staff to send. What does the draft do? When it does several, pick the one listed last.', ChatAiRiskEnum::definitions()),
            'answers_all' => [
                'type'         => 'noul',
                'instructions' => self::CONTEXT.' Does "draft" answer every question in "customer_wrote", leaving nothing for later?',
                'criteria'     => ['true' => 'Every question is answered and nothing is left blank or in [[double brackets]]', 'false' => 'A question is not answered, is left for later, or a [[gap]] is left for staff'],
            ],
        ]);

        if (!$answers) {
            return null;
        }

        $check = [
            'intent'        => Arr::get($answers, 'intent.choice'),
            'intent_p'      => round((float) Arr::get($answers, 'intent.probabilities.'.Arr::get($answers, 'intent.choice'), 0), 3),
            'risk'          => Arr::get($answers, 'risk.choice'),
            'risk_p'        => round((float) Arr::get($answers, 'risk.probabilities.'.Arr::get($answers, 'risk.choice'), 0), 3),
            'answers_all_p' => round((float) Arr::get($answers, 'answers_all.noul', 0), 3),
        ];

        $draft->update(['jev_check' => array_merge($draft->jev_check ?? [], $check)]);

        return $check;
    }

    /**
     * How the customer took the reply staff sent from this draft, read from the first thing they
     * wrote after it. Null while they have not written again. Only for a reply that came from the
     * draft (used or edited): what a customer makes of staff's own words says nothing about it.
     */
    public function reaction(ChatAiDraft $draft): ?ChatAiReactionEnum
    {
        $reply = self::message($draft, $draft->reply_message_id);
        if (!$reply || !in_array($draft->status, [ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED], true)) {
            return null;
        }

        $next = $reply->newQuery()
            ->where($draft->chat_session_id ? 'chat_session_id' : 'meta_chat_session_id', $draft->chat_session_id ?? $draft->meta_chat_session_id)
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->where('id', '>', $reply->id)
            ->orderBy('id')
            ->first();

        if (self::text($next) === '') {
            return null;
        }

        $answer   = AskJev::make()->choice(
            ['we_said' => mb_substr(self::text($reply), 0, 2000), 'customer_wrote' => mb_substr(self::text($next), 0, 3000)],
            self::CONTEXT.' "customer_wrote" is what the customer wrote after our reply "we_said"; emails can quote older messages below the new text, judge only the new text. How did they take our reply?',
            ChatAiReactionEnum::definitions()
        );
        $reaction = ChatAiReactionEnum::tryFrom((string) ($answer['choice'] ?? ''));

        if ($reaction) {
            $draft->update(['customer_reaction' => $reaction->value]);
        }

        return $reaction;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $since  = now()->subDays((int) $command->option('days'));
        $budget = (int) $command->option('limit');
        $assistant = fn ($query) => $query->where('facts->mode', DraftChatReply::ASSISTANT)->where('created_at', '>=', $since);

        $judged = 0;
        ChatAiDraft::where($assistant)->whereNull('jev_check')->whereNotNull('trigger_message_id')
            ->chunkById(100, function ($drafts) use (&$judged, &$budget) {
                foreach ($drafts as $draft) {
                    if ($budget-- <= 0) {
                        return false;
                    }
                    $judged += (int) (bool) $this->handle($draft);
                }

                return true;
            });

        $customerWroteAgain = fn (string $messages, string $session) => fn ($query) => $query->selectRaw('1')->from($messages)
            ->whereColumn("$messages.$session", "chat_ai_drafts.$session")
            ->whereIn("$messages.sender_type", [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])
            ->whereColumn("$messages.id", '>', 'chat_ai_drafts.reply_message_id');

        $reactions = 0;
        ChatAiDraft::where($assistant)->whereNull('customer_reaction')->whereNotNull('reply_message_id')
            ->whereIn('status', [ChatAiDraftStatusEnum::USED, ChatAiDraftStatusEnum::EDITED])
            ->where(fn ($wroteAgain) => $wroteAgain->whereExists($customerWroteAgain('chat_messages', 'chat_session_id'))
                ->orWhereExists($customerWroteAgain('meta_chat_messages', 'meta_chat_session_id')))
            ->chunkById(100, function ($drafts) use (&$reactions, &$budget) {
                foreach ($drafts as $draft) {
                    if ($budget-- <= 0) {
                        return false;
                    }
                    $reactions += (int) (bool) $this->reaction($draft);
                }

                return true;
            });

        $command->info("Judged $judged drafts and read $reactions customer reactions.");

        return 0;
    }

    private static function message(ChatAiDraft $draft, ?int $id): ChatMessage|MetaChatMessage|null
    {
        if (!$id) {
            return null;
        }

        return $draft->chat_session_id ? ChatMessage::find($id) : MetaChatMessage::find($id);
    }

    private static function text(ChatMessage|MetaChatMessage|null $message): string
    {
        return trim((string) $message?->message_text);
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function choice(string $question, array $options): array
    {
        return ['type' => 'choice', 'instructions' => self::CONTEXT.' '.$question, 'criteria' => $options];
    }
}
