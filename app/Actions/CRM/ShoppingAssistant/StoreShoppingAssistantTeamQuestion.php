<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\StoreStaffTask;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
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
 * (assistant_questions.user_id), as a staff task and a pop-up with an answer box in aiku: to the
 * guru when they have aiku open, else the backup, else a customer services agent online for the
 * shop, else the guru anyway (route()). The guru not answering within BACKUP_AFTER_MINUTES: the
 * backup person gets it too, and the first answer wins
 * (AnswerShoppingAssistantTeamQuestion). In working hours the customer's page waits WAIT_MINUTES
 * for it; a later answer reaches them through the conversation and by email or customer services.
 * One open question per conversation and MAX_PER_DAY per customer, as a customer's words put a
 * pop-up on a named person's screen. Off when the organisation has no question person.
 */
class StoreShoppingAssistantTeamQuestion
{
    use AsAction;

    public const string TASK_KIND = 'shopping_assistant_team_question';

    public const int BACKUP_AFTER_MINUTES = 3;

    public const int WAIT_MINUTES = 5;

    public const int MAX_PER_DAY = 3;

    public const string ONLINE_CACHE_KEY = 'shopping_assistant:online_user_ids';

    /**
     * @return array{status: 'created'|'already_asked'|'limit'|'off', task: ?StaffTask, live: bool}
     */
    public function handle(ShoppingAssistantConversation $conversation, string $questionInEnglish, string $customerWords): array
    {
        $customer     = $conversation->customer;
        $organisation = $customer?->organisation;

        if (!$organisation || !self::personOf($organisation)) {
            return ['status' => 'off', 'task' => null, 'live' => false];
        }

        $open = self::openFor($conversation);
        if ($open) {
            return ['status' => 'already_asked', 'task' => $open, 'live' => self::isAwaited($open)];
        }

        $today = StaffTask::where('data->kind', self::TASK_KIND)->where('data->customer_id', $customer->id)->where('created_at', '>=', now()->subDay())->count();
        if ($today >= self::MAX_PER_DAY) {
            return ['status' => 'limit', 'task' => null, 'live' => false];
        }

        $question  = mb_substr(trim($questionInEnglish), 0, 500);
        $words     = mb_substr(trim($customerWords), 0, 1000);
        $language  = DB::table('languages')->where('code', $conversation->language_code)->value('name') ?: $conversation->language_code;
        $person    = self::personOf($organisation);
        $backup    = self::backupOf($organisation);
        [$first, $routedTo] = self::route($person, $backup, $customer->shop_id);
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
            'collaborator_ids' => array_values(array_filter([$backup?->id, $person->id !== $first->id ? $person->id : null])),
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
        if ($first->id === $person->id && $backup && $backup->id !== $person->id) {
            AlertShoppingAssistantTeamQuestion::dispatch($task, $backup)->delay(now()->addMinutes(self::BACKUP_AFTER_MINUTES));
        }

        return ['status' => 'created', 'task' => $task, 'live' => $live];
    }

    /**
     * Who gets the question first, by who has aiku open now: the guru, else the backup, else a
     * customer services agent online for the shop, else the guru anyway (it waits as a task).
     *
     * @return array{0: User, 1: 'guru'|'backup'|'customer_services'|'nobody_online'}
     */
    public static function route(User $person, ?User $backup, int $shopId): array
    {
        $online = self::onlineUserIds();
        $isOn   = fn (?User $user) => $user && ($online === null || in_array($user->id, $online, true));

        if ($isOn($person)) {
            return [$person, 'guru'];
        }
        if ($isOn($backup)) {
            return [$backup, 'backup'];
        }
        $agent = ChatAgent::findAvailableAgent(null, $shopId)?->user;
        if ($agent && $agent->status) {
            return [$agent, 'customer_services'];
        }

        return [$person, 'nobody_online'];
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

    public static function personOf(Organisation $organisation): ?User
    {
        return self::userOf($organisation, 'user_id');
    }

    public static function backupOf(Organisation $organisation): ?User
    {
        return self::userOf($organisation, 'backup_user_id');
    }

    public static function openFor(ShoppingAssistantConversation $conversation): ?StaffTask
    {
        return StaffTask::where('data->kind', self::TASK_KIND)
            ->where('data->conversation_id', $conversation->id)
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
        return [
            'task'           => $task->reference,
            'question'       => (string) data_get($task->data, 'question'),
            'customer_words' => (string) data_get($task->data, 'customer_words'),
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
     * The open questions waiting for this person: theirs (the assignee), or as the backup once the
     * backup is due, never one they said they do not want to answer.
     *
     * @return \Illuminate\Support\Collection<int, StaffTask>
     */
    public static function waitingFor(User $user): \Illuminate\Support\Collection
    {
        return StaffTask::where('data->kind', self::TASK_KIND)
            ->whereNull('data->answered_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->limit(20)
            ->get()
            ->filter(function (StaffTask $task) use ($user) {
                if (in_array($user->id, data_get($task->data, 'declined_by', []), true)) {
                    return false;
                }
                if ($task->assignee_id === $user->id) {
                    return true;
                }
                $organisation = Organisation::find(data_get($task->data, 'organisation_id'));

                return $organisation && self::backupOf($organisation)?->id === $user->id
                    && ($task->created_at->lte(now()->subMinutes(self::BACKUP_AFTER_MINUTES)) || in_array($task->assignee_id, data_get($task->data, 'declined_by', []), true));
            })
            ->values();
    }

    public static function canAnswer(StaffTask $task, User $user): bool
    {
        $organisation = Organisation::find(data_get($task->data, 'organisation_id'));

        return data_get($task->data, 'kind') === self::TASK_KIND
            && ($task->assignee_id === $user->id || ($organisation && in_array($user->id, [self::personOf($organisation)?->id, self::backupOf($organisation)?->id], true)));
    }

    private static function userOf(Organisation $organisation, string $key): ?User
    {
        $id = Arr::get($organisation->settings, 'assistant_questions.'.$key);

        return $id ? User::where('id', $id)->where('status', true)->first() : null;
    }
}
