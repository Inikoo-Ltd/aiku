<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Tasks\GetAikuAssistant;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\ShoppingAssistantMorningRoundNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * At the start of each working day every guru gets one message with the questions customers asked
 * their shops that nobody has answered, and how many customers their answers helped in the last
 * week. A question that was already in an earlier day's round goes to the organisation's managers
 * as well, saying how long the customer has waited and when it closes (a day after they are told). Each question is stamped with its first round (round_first_at), which the closing rule
 * counts from (CloseUnansweredShoppingAssistantTeamQuestions). Runs every 15 minutes and sends once
 * per organisation per day, at the first run of a working day in the organisation's time. The
 * answers the researcher sent since the last round are listed too, for the gurus to confirm or to
 * correct on the shop's notes, and a few facts the AI does not use until somebody says yes. It is a message, never a pop-up that blocks the screen (INI-257).
 */
class SendShoppingAssistantMorningRound
{
    use AsAction;

    public const int HELPED_DAYS = 7;

    public const int LEARNED_FACTS_PER_SHOP = 3;

    public const int LEARNED_FACTS_PER_GURU = 5;

    public const int NOTES_PER_ROUND = 20;

    public string $commandSignature = 'shopping-assistant:morning-round';

    public string $commandDescription = 'Send each guru the questions to our team nobody answered, once at the start of the working day';

    public function handle(): int
    {
        $sent = 0;

        $open = StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)
            ->whereNull('data->answered_at')
            ->oldest('id')
            ->get()
            ->filter(fn (StaffTask $task) => $task->status->isOpen())
            ->groupBy(fn (StaffTask $task) => (int) data_get($task->data, 'organisation_id'));

        foreach (Organisation::all() as $organisation) {
            $tasks     = $open->get($organisation->id, collect());
            $toConfirm = ResearchShoppingAssistantTeamQuestion::toConfirm($organisation->id);
            $facts     = $this->factsWaiting($organisation);
            if (($tasks->isEmpty() && $toConfirm->isEmpty() && $facts->isEmpty()) || !RemindShoppingAssistantSalesLeads::make()->isWorkingTime($organisation)) {
                continue;
            }

            $startOfDay = now()->setTimezone($organisation->timezone?->name ?? 'UTC')->startOfDay();
            if (!Cache::add('shopping_assistant:morning_round:'.$organisation->id.':'.$startOfDay->toDateString(), true, now()->addDays(2))) {
                continue;
            }

            try {
                $sent += $this->sendRound($organisation, $tasks, $toConfirm, CarbonImmutable::parse($startOfDay), $facts);
            } catch (Throwable $exception) {
                Cache::forget('shopping_assistant:morning_round:'.$organisation->id.':'.$startOfDay->toDateString());
                Log::error('Merlin morning round of organisation '.$organisation->id.' failed: '.$exception->getMessage());
            }
        }

        return $sent;
    }

    /**
     * @param  Collection<int, StaffTask>  $tasks
     * @param  Collection<int, StaffTask>  $toConfirm
     * @param  Collection<int, ChatKnowledgeEntry>|null  $facts
     */
    public function sendRound(Organisation $organisation, Collection $tasks, Collection $toConfirm, CarbonImmutable $startOfDay, ?Collection $facts = null): int
    {
        $sent      = 0;
        $byUser    = [];
        $confirmBy = [];
        $factsBy   = [];
        $users     = [];
        $overdue = $tasks->filter(fn (StaffTask $task) => !data_get($task->data, 'managers_told_at') && ($first = data_get($task->data, 'round_first_at')) && CarbonImmutable::parse($first)->lt($startOfDay))->values();

        foreach ($tasks as $task) {
            foreach ($this->answerersOf($task) as $user) {
                $users[$user->id]    = $user;
                $byUser[$user->id][] = $task;
            }
        }

        foreach ($toConfirm as $task) {
            foreach (StoreShoppingAssistantTeamQuestion::gurusOfTask($task) as $user) {
                $users[$user->id]       = $user;
                $byUser[$user->id]    ??= [];
                $confirmBy[$user->id][] = $task;
            }
        }

        foreach ($facts ?? [] as $fact) {
            foreach (StoreShoppingAssistantTeamQuestion::gurusOf($organisation, $fact->shop_id) as $user) {
                $learnedSoFar = collect($factsBy[$user->id] ?? [])->where('source_type', 'learned')->count();
                if ($fact->created_by_user_id !== $user->id && ($fact->source_type !== 'learned' || $learnedSoFar < self::LEARNED_FACTS_PER_GURU)) {
                    $users[$user->id]     = $user;
                    $byUser[$user->id]  ??= [];
                    $factsBy[$user->id][] = $fact;
                }
            }
        }

        foreach ($byUser as $userId => $userTasks) {
            $notification = new ShoppingAssistantMorningRoundNotification(collect($userTasks), $this->helpedCustomers($userId), false, collect($confirmBy[$userId] ?? []), collect($factsBy[$userId] ?? []));
            $sent += $this->notify($users[$userId], $notification);
        }

        if ($overdue->isNotEmpty()) {
            $notification = new ShoppingAssistantMorningRoundNotification($overdue, null, true);
            foreach (StoreShoppingAssistantSalesLead::managersOf($organisation, GetAikuAssistant::run($organisation->group_id)) as $manager) {
                $sent += $this->notify($manager, $notification);
            }
        }

        $overdueIds = $overdue->pluck('id')->all();
        foreach ($tasks as $task) {
            self::stamp($task, fn (array $data) => [
                ...$data,
                'round_first_at' => data_get($data, 'round_first_at') ?? now()->toIso8601String(),
                'rounds'         => (int) data_get($data, 'rounds', 0) + 1,
            ] + (in_array($task->id, $overdueIds, true) ? ['managers_told_at' => data_get($data, 'managers_told_at') ?? now()->toIso8601String()] : []));
        }

        foreach ($toConfirm as $task) {
            self::stamp($task, fn (array $data) => [...$data, 'researcher_answer_listed_at' => now()->toIso8601String()]);
        }

        return $sent;
    }

    /**
     * One person's message; a failure (their email bounced, Slack down) is logged and the round goes on,
     * so the questions are still stamped and the others are not sent the round again.
     */
    private function notify(User $user, ShoppingAssistantMorningRoundNotification $notification): int
    {
        try {
            NotifyUserOnChosenChannels::run($user, UserNotificationEnum::ASSISTANT_QUESTION, $notification, $notification->title(), $notification->summary(), $notification->url());

            return 1;
        } catch (Throwable $exception) {
            Log::warning('Merlin morning round to user '.$user->id.' failed: '.$exception->getMessage());

            return 0;
        }
    }

    /**
     * Changes the task's data on its latest copy, locked, so an answer or a claim written meanwhile is kept.
     *
     * @param  callable(array): array  $change
     */
    public static function stamp(StaffTask $task, callable $change): void
    {
        DB::transaction(function () use ($task, $change) {
            $locked = StaffTask::whereKey($task->id)->lockForUpdate()->first();
            $locked->update(['data' => $change($locked->data)]);
            $task->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Facts the AI does not use until someone says yes: answers kept as shop notes that wait for a
     * second person, and the LEARNED_FACTS_PER_SHOP agents' answers most customers were told.
     *
     * @return Collection<int, ChatKnowledgeEntry>
     */
    public function factsWaiting(Organisation $organisation): Collection
    {
        $notes   = ChatKnowledgeEntry::with('shop')->where('organisation_id', $organisation->id)->where('is_manual', true)->where('status', 'proposed')->oldest('id')->limit(self::NOTES_PER_ROUND)->get();
        $learned = ChatKnowledgeEntry::with('shop')->where('organisation_id', $organisation->id)->where('source_type', 'learned')->where('status', 'proposed')
            ->orderByDesc('customers_count')->orderBy('id')->get()
            ->groupBy('shop_id')
            ->flatMap(fn (Collection $shopFacts) => $shopFacts->take(self::LEARNED_FACTS_PER_SHOP));

        return $notes->concat($learned)->filter(fn (ChatKnowledgeEntry $fact) => $fact->shop)->values();
    }

    /**
     * The gurus of the question's shop and whoever holds it, less anyone who said they do not want to answer it.
     *
     * @return array<int, User>
     */
    private function answerersOf(StaffTask $task): array
    {
        $declined = data_get($task->data, 'declined_by', []);
        $people   = collect([...StoreShoppingAssistantTeamQuestion::gurusOfTask($task), $task->assignee])
            ->filter(fn (?User $user) => $user && $user->status && !in_array($user->id, $declined, true))
            ->unique('id');

        return $people->values()->all();
    }

    /**
     * Customers who, in the last HELPED_DAYS days, got a Merlin answer citing a note this person wrote.
     */
    public function helpedCustomers(int $userId): int
    {
        return (int) DB::table('shopping_assistant_messages')
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_messages.shopping_assistant_conversation_id')
            ->where('shopping_assistant_messages.created_at', '>=', now()->subDays(self::HELPED_DAYS))
            ->whereNotNull('shopping_assistant_messages.sources')
            ->whereRaw(
                "exists (select 1 from jsonb_array_elements(shopping_assistant_messages.sources) as source join chat_knowledge_entries on chat_knowledge_entries.id = (source->>'id')::bigint where chat_knowledge_entries.is_manual and chat_knowledge_entries.created_by_user_id = ?)",
                [$userId]
            )
            ->distinct()
            ->count('shopping_assistant_conversations.customer_id');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Sent '.$this->handle().' morning round messages');

        return 0;
    }
}
