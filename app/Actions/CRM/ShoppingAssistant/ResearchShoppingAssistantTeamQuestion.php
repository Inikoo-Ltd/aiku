<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Helpers\AI\AskJev;
use App\Actions\Tasks\GetAikuAssistant;
use App\Models\Catalogue\Shop;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The researcher: a Claude routine on the subscription, run from several accounts, that works the
 * questions Merlin passed to our team before a guru has to (INI-257). It claims questions so two
 * runs never work the same one, then answers a question only when every statement has evidence
 * it names (a query and its result, a file and method, a guide and section) and an independent
 * Jev check finds the answer follows from that evidence (ANSWER_CONFIDENCE); anything else is
 * left as a draft with its evidence, which the guru's pop-up shows pre-filled. Every answer it
 * sends is listed in the next morning round for the gurus to confirm or correct.
 */
class ResearchShoppingAssistantTeamQuestion
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const int CLAIM_MINUTES = 20;

    public const float ANSWER_CONFIDENCE = 0.9;

    public const int MIN_EVIDENCE_CHARACTERS = 40;

    /**
     * The open questions worth researching now, oldest first, of the shops whose chats the user
     * works: not claimed by another run, and not already drafted unless the customer asked more
     * since or the draft is over a day old.
     *
     * @return Collection<int, StaffTask>
     */
    public static function open(User $user, string $run, int $limit): Collection
    {
        $research = self::make();

        return StaffTask::where('group_id', $user->group_id)
            ->where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)
            ->whereNull('data->answered_at')
            ->oldest('id')
            ->get()
            ->filter(fn (StaffTask $task) => $research->mayResearch($user, $task) && !self::claimedByAnother($task, $run) && self::needsResearch($task))
            ->take($limit)
            ->values();
    }

    /**
     * A question still open, of a shop whose chats the user works, as for the customer service tools.
     */
    public function mayResearch(User $user, StaffTask $task): bool
    {
        $shop = Shop::find(data_get($task->data, 'shop_id'));
        setPermissionsTeamId($user->group_id);

        return $task->group_id === $user->group_id
            && $task->status->isOpen()
            && !data_get($task->data, 'answered_at')
            && $shop
            && $this->userCanActOnChatOnShop($user, $shop);
    }

    public static function claimedByAnother(StaffTask $task, string $run): bool
    {
        $claim = data_get($task->data, 'research_claim');

        return $claim && $claim['run'] !== $run && CarbonImmutable::parse($claim['until'])->isFuture();
    }

    public static function needsResearch(StaffTask $task): bool
    {
        $draftedAt = data_get($task->data, 'drafted_at');

        return !$draftedAt
            || count(data_get($task->data, 'more_questions', [])) > (int) data_get($task->data, 'drafted_questions', 0)
            || CarbonImmutable::parse($draftedAt)->lt(now()->subDay());
    }

    /**
     * Claims the question for this run for CLAIM_MINUTES; false when another run holds it.
     */
    public static function claim(StaffTask $task, string $run): bool
    {
        return DB::transaction(function () use ($task, $run) {
            $locked = StaffTask::whereKey($task->id)->lockForUpdate()->first();
            if (data_get($locked->data, 'answered_at') || self::claimedByAnother($locked, $run)) {
                return false;
            }
            $locked->update(['data' => [...$locked->data, 'research_claim' => ['run' => $run, 'until' => now()->addMinutes(self::CLAIM_MINUTES)->toIso8601String()]]]);
            $task->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * What the routine reads to research a question.
     *
     * @return array<string, mixed>
     */
    public static function row(StaffTask $task, bool $withConversation): array
    {
        $conversation = ShoppingAssistantConversation::with('website')->find(data_get($task->data, 'conversation_id'));

        return array_filter([
            'reference'      => $task->reference,
            'shop'           => data_get($task->data, 'shop_code'),
            'website'        => $conversation?->website?->domain,
            'language'       => data_get($task->data, 'language'),
            'asked'          => $task->created_at->diffForHumans(),
            'customer'       => data_get($task->data, 'customer_name'),
            'questions'      => StoreShoppingAssistantTeamQuestion::questionsOf($task),
            'routed_to'      => data_get($task->data, 'routed_to'),
            'declined_by'    => count(data_get($task->data, 'declined_by', [])) ?: null,
            'draft'          => data_get($task->data, 'draft_answer'),
            'draft_evidence' => data_get($task->data, 'draft_evidence'),
            'conversation'   => $withConversation && $conversation && !$conversation->wiped_at
                ? $conversation->messages()->latest('id')->limit(10)->get()->reverse()->map(fn (ShoppingAssistantMessage $message) => [
                    'role' => $message->role->value,
                    'body' => mb_substr((string) $message->body, 0, 1500),
                ])->values()->all()
                : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public static function saveDraft(StaffTask $task, User $user, string $answer, string $evidence, ?string $reason = null): bool
    {
        $saved = DB::transaction(function () use ($task, $user, $answer, $evidence) {
            $locked = StaffTask::whereKey($task->id)->lockForUpdate()->first();
            if (data_get($locked->data, 'answered_at')) {
                return false;
            }
            $locked->update(['data' => [
                ...$locked->data,
                'draft_answer'      => trim($answer),
                'draft_evidence'    => trim($evidence),
                'drafted_at'        => now()->toIso8601String(),
                'drafted_by'        => $user->id,
                'drafted_questions' => count(data_get($locked->data, 'more_questions', [])),
                'research_claim'    => null,
            ]]);
            $task->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if (!$saved) {
            return false;
        }

        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, GetAikuAssistant::run($task->group_id), ['body' => implode("\n", array_filter([
                __('The researcher left a draft answer for you to check: it is in the answer box of the pop-up.'),
                $reason,
                __('Evidence: :evidence', ['evidence' => trim($evidence)]),
            ]))]);
        }

        return true;
    }

    /**
     * Sends the answer only when the evidence carries it; else it is kept as a draft for the guru.
     *
     * @return array{result: 'answered'|'drafted'|'already_answered'|'not_an_answer'|'not_a_question', confidence: ?float}
     */
    public static function answer(StaffTask $task, User $user, string $answer, string $evidence, bool $keepForEveryone): array
    {
        $confidence = self::followsFromEvidence($task, $answer, $evidence);
        if ($confidence === null || $confidence < self::ANSWER_CONFIDENCE) {
            $drafted = self::saveDraft($task, $user, $answer, $evidence, __('An independent check was not sure the evidence proves this answer, so it was not sent.'));

            return ['result' => $drafted ? 'drafted' : 'already_answered', 'confidence' => $confidence];
        }

        $result = AnswerShoppingAssistantTeamQuestion::run($task, $user, $answer, $keepForEveryone);
        if ($result === 'answered') {
            SendShoppingAssistantMorningRound::stamp($task, fn (array $data) => [...$data, 'answered_by_researcher' => true, 'answer_evidence' => trim($evidence), 'research_claim' => null]);
        }

        return ['result' => $result, 'confidence' => $confidence];
    }

    public static function followsFromEvidence(StaffTask $task, string $answer, string $evidence): ?float
    {
        return AskJev::make()->noul(
            ['customer_question' => StoreShoppingAssistantTeamQuestion::questionText($task), 'customers_words' => StoreShoppingAssistantTeamQuestion::questionText($task, 'customer_words'), 'answer' => $answer, 'evidence' => $evidence],
            'An assistant wants to send this answer to a customer of our shop. Does every statement in the answer follow from the evidence, for the question as the customer meant it?',
            'Yes: each statement is proven by the evidence (data queried, our code, a published guide), not only likely; the meaning of the question is clear; and the answer makes no promise, exception or policy decision and states no price or stock figure that is not in the evidence.',
            'No: some statement is not proven by the evidence, is only likely, rests on a guess about what the customer meant, or is policy, an exception, a promise, or a figure not in the evidence.'
        );
    }

    /**
     * The answers the researcher sent that no morning round has listed yet, for the gurus to confirm or correct.
     *
     * @return Collection<int, StaffTask>
     */
    public static function toConfirm(int $organisationId): Collection
    {
        return StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)
            ->where('data->organisation_id', $organisationId)
            ->where('data->answered_by_researcher', true)
            ->whereNull('data->researcher_answer_listed_at')
            ->oldest('id')
            ->get();
    }
}
