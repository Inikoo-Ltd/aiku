<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * A question the AI shopping assistant could not answer from our texts (samples, discount codes, a
 * document that is not online...) goes to the organisation's question person, set in its settings
 * (assistant_questions.user_id), as a staff task and a pop-up with an answer box in aiku. A shop
 * may set its own guru (shop settings assistant_questions.user_id), asked before the organisation's
 * guru and backup (gurusOf()). It goes to the first guru in that order with aiku open, else a
 * customer services agent online for the shop, else the first guru anyway (route()). Not answered
 * within BACKUP_AFTER_MINUTES: every other guru gets it too, also the ones who were not online and
 * all of them when it went to customer services, who rarely know the answer, and the first answer wins
 * (AnswerShoppingAssistantTeamQuestion). In working hours the customer's page waits WAIT_MINUTES
 * for it; a later answer reaches them through the conversation and by email or customer services.
 * One waiting task per customer, which their further questions join (join()), and MAX_PER_DAY
 * tasks per customer, as a customer's words put a pop-up on a named person's screen. Off when
 * neither the shop nor the organisation has a guru.
 */
class StoreShoppingAssistantTeamQuestion
{
    use AsAction;

    public const string TASK_KIND = 'shopping_assistant_team_question';

    public const int BACKUP_AFTER_MINUTES = 3;

    public const int WAIT_MINUTES = 5;

    public const int MAX_PER_DAY = 3;

    public const int MAX_QUESTIONS_PER_TASK = 5;

    public const string ONLINE_CACHE_KEY = 'shopping_assistant:online_user_ids';

    /**
     * A question that reports something of ours not working goes to the engineers as a MER ticket
     * instead (StoreShoppingAssistantEngineerTicket::fromQuestion): status to_engineers, with the ticket.
     * Not when the stricter report_to_engineers check already refused it in this turn ($mayBeFault
     * false): that refusal means the customer should try the documented fix or talk to a person first.
     *
     * @return array{status: 'created'|'already_asked'|'limit'|'off'|'to_engineers', task: ?StaffTask, live: bool, ticket?: \App\Models\Helpers\Ticket, already_reported?: bool}
     */
    public function handle(ShoppingAssistantConversation $conversation, string $questionInEnglish, string $customerWords, bool $mayBeFault = true): array
    {
        $customer     = $conversation->customer;
        $organisation = $customer?->organisation;

        if ($customer && $mayBeFault) {
            $fault = StoreShoppingAssistantEngineerTicket::make()->fromQuestion($conversation, mb_substr(trim($questionInEnglish), 0, 500), mb_substr(trim($customerWords), 0, 1000));
            if ($fault['ticket']) {
                return ['status' => 'to_engineers', 'task' => null, 'live' => false, 'ticket' => $fault['ticket'], 'already_reported' => $fault['status'] === 'already_reported'];
            }
        }

        $gurus        = $organisation ? self::gurusOf($organisation, $customer->shop_id) : [];

        if (!$gurus) {
            return ['status' => 'off', 'task' => null, 'live' => false];
        }

        $question = mb_substr(trim($questionInEnglish), 0, 500);
        $words    = mb_substr(trim($customerWords), 0, 1000);
        $language = DB::table('languages')->where('code', $conversation->language_code)->value('name') ?: $conversation->language_code;

        $open = self::openFor($conversation) ?? self::openForCustomer($customer->id);
        if ($open) {
            return $this->join($open, $conversation, $question, $words, $language)
                ? ['status' => 'already_asked', 'task' => $open, 'live' => self::isAwaited($open)]
                : ['status' => 'limit', 'task' => null, 'live' => false];
        }

        $today = StaffTask::where('data->kind', self::TASK_KIND)->where('data->customer_id', $customer->id)->where('created_at', '>=', now()->subDay())->count();
        if ($today >= self::MAX_PER_DAY) {
            return ['status' => 'limit', 'task' => null, 'live' => false];
        }

        [$first, $routedTo] = self::route($gurus, $customer->shop_id);
        $live      = $routedTo !== 'nobody_online' && RemindShoppingAssistantSalesLeads::make()->isWorkingTime($organisation);
        $assistant = GetAikuAssistant::run($customer->group_id);

        $task = StoreStaffTask::run($assistant, [
            'subject'          => __('Question from :customer: :question', ['customer' => $customer->name, 'question' => mb_substr($question, 0, 120)]),
            'description'      => implode("\n", array_filter([
                __('A customer asked the AI shopping assistant something our texts do not answer. Answer in the pop-up, or here in the note when you mark this task done: it is translated into their language and sent to them.'),
                __('Question: :question', ['question' => $question]),
                $words !== $question ? __('Their words (:language): :words', ['language' => $language, 'words' => $words]) : null,
                __('Customer: :name (:reference)', ['name' => $customer->name, 'reference' => $customer->reference]).' '.route('grp.org.shops.show.crm.customers.show', [$organisation->slug, $customer->shop->slug, $customer->slug]),
                $conversation->website ? __('Conversation on :website:', ['website' => $conversation->website->domain]).' '.route('grp.org.shops.show.web.analytics.shopping_assistant.conversations.show', [$organisation->slug, $customer->shop->slug, $conversation->website->slug, $conversation->ulid]) : null,
            ])),
            'assignee_id'      => $first->id,
            'collaborator_ids' => array_values(array_filter(array_map(fn (User $guru) => $guru->id !== $first->id ? $guru->id : null, $gurus))),
            'priority'         => ChatPriorityEnum::NORMAL->value,
            'model_type'       => 'Customer',
            'model_id'         => $customer->id,
            'notify'           => false,
            'data'             => [
                'kind'              => self::TASK_KIND,
                'organisation_id'   => $organisation->id,
                'shop_id'           => $customer->shop_id,
                'conversation_id'   => $conversation->id,
                'conversation_ulid' => $conversation->ulid,
                'customer_id'       => $customer->id,
                'customer_name'     => $customer->name,
                'shop_code'         => $customer->shop->code,
                'question'          => $question,
                'customer_words'    => $words,
                'language'          => $language,
                'wait_until'        => $live ? now()->addMinutes(self::WAIT_MINUTES)->toIso8601String() : null,
                'routed_to'         => $routedTo,
            ],
        ]);

        AlertShoppingAssistantTeamQuestion::dispatch($task, $first);
        foreach ($gurus as $backup) {
            if ($backup->id !== $first->id) {
                AlertShoppingAssistantTeamQuestion::dispatch($task, $backup)->delay(now()->addMinutes(self::BACKUP_AFTER_MINUTES));
            }
        }

        return ['status' => 'created', 'task' => $task, 'live' => $live];
    }

    /**
     * A customer who already has a question waiting gets no second task: every question typed in the
     * search box starts a conversation of its own, so three questions in a row used the day's three
     * tasks and the fourth was refused. A different question joins the waiting task instead, up to
     * MAX_QUESTIONS_PER_TASK, shown to the person in the pop-up and in the task's thread; the same
     * question again adds nothing. The task follows the conversation the customer asked from last,
     * so the answer appears where they are now. False when the task is full.
     */
    private function join(StaffTask $task, ShoppingAssistantConversation $conversation, string $question, string $words, string $language): bool
    {
        $asked  = self::questionsOf($task);
        $isNew  = !collect($asked)->contains(fn (array $one) => mb_strtolower($one['question']) === mb_strtolower($question) || mb_strtolower($one['customer_words']) === mb_strtolower($words));
        $data   = $task->data;

        if ($isNew && count($asked) >= self::MAX_QUESTIONS_PER_TASK) {
            return false;
        }

        if ($isNew) {
            $data['more_questions'] = [...data_get($data, 'more_questions', []), ['question' => $question, 'customer_words' => $words, 'language' => $language, 'asked_at' => now()->toIso8601String()]];
            unset($data['round_first_at'], $data['managers_told_at']);
        }

        $also = implode("\n", array_filter([
            __('The customer also asked: :question', ['question' => $question]),
            $words !== $question ? __('Their words (:language): :words', ['language' => $language, 'words' => $words]) : null,
        ]));

        $task->update(array_filter([
            'data'        => [...$data, 'conversation_id' => $conversation->id, 'conversation_ulid' => $conversation->ulid],
            'description' => $isNew ? trim($task->description."\n".$also) : null,
        ]));

        if ($isNew) {
            if ($task->conversation) {
                SendStaffMessage::run($task->conversation, GetAikuAssistant::run($task->group_id), ['body' => $also]);
            }
            if ($task->assignee) {
                AlertShoppingAssistantTeamQuestion::dispatch($task, $task->assignee);
            }
        }

        return true;
    }

    /**
     * Every question waiting on the task, the first one and the ones that joined it.
     *
     * @return array<int, array{question: string, customer_words: string}>
     */
    public static function questionsOf(StaffTask $task): array
    {
        return array_map(
            fn (array $one) => ['question' => (string) ($one['question'] ?? ''), 'customer_words' => (string) ($one['customer_words'] ?? '')],
            [['question' => data_get($task->data, 'question'), 'customer_words' => data_get($task->data, 'customer_words')], ...data_get($task->data, 'more_questions', [])]
        );
    }

    /**
     * The questions as one text for the person answering and for the note kept for the shop:
     * the question itself when there is one, a numbered list when more joined it.
     */
    public static function questionText(StaffTask $task, string $field = 'question'): string
    {
        $texts = array_values(array_filter(array_column(self::questionsOf($task), $field)));

        return count($texts) > 1
            ? implode("\n", array_map(fn (string $text, int $index) => ($index + 1).'. '.$text, $texts, array_keys($texts)))
            : ($texts[0] ?? '');
    }

    /**
     * Who gets the question first, by who has aiku open now: the first guru in order, else a
     * customer services agent online for the shop, else the first guru anyway (it waits as a task).
     *
     * @param  array<int, User>  $gurus
     * @return array{0: User, 1: 'guru'|'backup'|'customer_services'|'nobody_online'}
     */
    public static function route(array $gurus, int $shopId): array
    {
        $online = self::onlineUserIds();

        foreach ($gurus as $index => $guru) {
            if ($online === null || in_array($guru->id, $online, true)) {
                return [$guru, $index === 0 ? 'guru' : 'backup'];
            }
        }
        $agent = ChatAgent::findAvailableAgent(null, $shopId)?->user;
        if ($agent && $agent->status) {
            return [$agent, 'customer_services'];
        }

        return [$gurus[0], 'nobody_online'];
    }

    /**
     * The users with aiku open now, from the members of the grp.live.users presence channel on
     * the websockets server; null when that cannot be read, and then everybody counts as online.
     *
     * @return ?array<int, int>
     */
    public static function onlineUserIds(): ?array
    {
        return Cache::remember(self::ONLINE_CACHE_KEY, 30, function () {
            try {
                $members = Broadcast::connection()->getPusher()->get('/channels/presence-grp.live.users/users');

                return array_values(array_map(fn ($member) => (int) (is_array($member) ? $member['id'] : $member->id), (array) data_get($members, 'users', [])));
            } catch (Throwable) {
                return null;
            }
        });
    }

    /**
     * Who answers a question from this shop, in the order they are asked: the shop's guru, then the
     * organisation's guru and its backup (the backup only counts with an organisation guru).
     *
     * @return array<int, User>
     */
    public static function gurusOf(Organisation $organisation, ?int $shopId): array
    {
        $shopGuruId         = (int) ($shopId ? Arr::get(Shop::find($shopId)?->settings, 'assistant_questions.user_id') : 0);
        $organisationGuruId = (int) Arr::get($organisation->settings, 'assistant_questions.user_id');
        $backupId           = (int) Arr::get($organisation->settings, 'assistant_questions.backup_user_id');
        $users              = User::whereIn('id', array_filter([$shopGuruId, $organisationGuruId, $backupId]))->where('status', true)->get()->keyBy('id');
        $ids                = array_unique(array_filter([$shopGuruId, $organisationGuruId, $users->has($organisationGuruId) ? $backupId : 0]));

        return array_values(array_filter(array_map(fn (int $id) => $users->get($id), $ids)));
    }

    /**
     * @return array<int, User>
     */
    public static function gurusOfTask(StaffTask $task): array
    {
        $organisation = Organisation::find(data_get($task->data, 'organisation_id'));

        return $organisation ? self::gurusOf($organisation, data_get($task->data, 'shop_id')) : [];
    }

    public static function openFor(ShoppingAssistantConversation $conversation): ?StaffTask
    {
        return StaffTask::where('data->kind', self::TASK_KIND)
            ->where('data->conversation_id', $conversation->id)
            ->whereNull('data->answered_at')
            ->latest('id')
            ->first();
    }

    public static function openForCustomer(int $customerId): ?StaffTask
    {
        return StaffTask::where('data->kind', self::TASK_KIND)
            ->where('data->customer_id', $customerId)
            ->whereNull('data->answered_at')
            ->latest('id')
            ->first();
    }

    /**
     * The customer's page keeps checking for the answer while this is true.
     */
    public static function isAwaited(StaffTask $task): bool
    {
        $until = data_get($task->data, 'wait_until');

        return $until && !data_get($task->data, 'answered_at') && now()->lt($until);
    }

    /**
     * What the pop-up shows and where its buttons post.
     *
     * @return array<string, string>
     */
    public static function popup(StaffTask $task): array
    {
        return array_filter([
            'draft'          => data_get($task->data, 'draft_answer'),
            'draft_evidence' => data_get($task->data, 'draft_evidence'),
        ]) + [
            'task'           => $task->reference,
            'question'       => self::questionText($task),
            'customer_words' => self::questionText($task, 'customer_words'),
            'language'       => (string) data_get($task->data, 'language'),
            'customer'       => (string) data_get($task->data, 'customer_name'),
            'shop'           => (string) data_get($task->data, 'shop_code'),
            'answer_url'     => route('grp.tasks.assistant_answer', $task->reference),
            'decline_url'    => route('grp.tasks.assistant_decline', $task->reference),
            'pass_url'       => route('grp.tasks.assistant_pass', $task->reference),
            'task_url'       => route('grp.tasks.show', $task->reference),
        ];
    }

    /**
     * The open questions waiting for this person: theirs (the assignee), or as one of the other
     * gurus once the backups are due, never one they said they do not want to answer.
     *
     * @return \Illuminate\Support\Collection<int, StaffTask>
     */
    public static function waitingFor(User $user): \Illuminate\Support\Collection
    {
        $backupIdsByShop = [];

        return StaffTask::where('data->kind', self::TASK_KIND)
            ->whereNull('data->answered_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(function (StaffTask $task) use ($user, &$backupIdsByShop) {
                if (in_array($user->id, data_get($task->data, 'declined_by', []), true)) {
                    return false;
                }
                if ($task->assignee_id === $user->id) {
                    return true;
                }
                $shopKey   = data_get($task->data, 'organisation_id').'-'.data_get($task->data, 'shop_id');
                $guruIds   = $backupIdsByShop[$shopKey] ??= array_map(fn (User $guru) => $guru->id, self::gurusOfTask($task));

                return in_array($user->id, $guruIds, true)
                    && ($task->created_at->lte(now()->subMinutes(self::BACKUP_AFTER_MINUTES)) || in_array($task->assignee_id, data_get($task->data, 'declined_by', []), true));
            })
            ->values();
    }

    public static function canAnswer(StaffTask $task, User $user): bool
    {
        return data_get($task->data, 'kind') === self::TASK_KIND
            && ($task->assignee_id === $user->id || in_array($user->id, array_map(fn (User $guru) => $guru->id, self::gurusOfTask($task)), true));
    }
}
