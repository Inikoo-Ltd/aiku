<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Tasks\GetAikuAssistant;
use App\Actions\Tasks\UpdateStaffTask;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "I don't want to answer" on a question pop-up, said on the task so the team sees who did not help.
 * From the guru, the question moves to the backup at once (instead of after BACKUP_AFTER_MINUTES).
 * From the backup, a colleague it was passed to, or a guru with no backup left: the task is closed
 * and the customer is told to contact customer services (Raul, 13 Nov 2026).
 */
class DeclineShoppingAssistantTeamQuestion
{
    use AsAction;

    /**
     * A guru who does not hold the question only steps aside: the holder keeps it. The holder
     * declining passes it to the next guru in order who has not declined, else it is closed.
     *
     * @return 'to_backup'|'closed'|'kept'|'ignored'
     */
    public function handle(StaffTask $task, User $user): string
    {
        if (data_get($task->data, 'answered_at') || in_array($user->id, data_get($task->data, 'declined_by', []), true)) {
            return 'ignored';
        }

        $declined     = [...data_get($task->data, 'declined_by', []), $user->id];
        $assistant    = GetAikuAssistant::run($task->group_id);
        $gurus        = StoreShoppingAssistantTeamQuestion::gurusOfTask($task);
        $position     = array_search($user->id, array_map(fn (User $guru) => $guru->id, $gurus), true);
        $holderKeeps  = $task->assignee_id && $task->assignee_id !== $user->id && !in_array($task->assignee_id, $declined, true);
        $backup       = !$holderKeeps && $position !== false
            ? collect([...array_slice($gurus, $position + 1), ...array_slice($gurus, 0, $position)])->first(fn (User $guru) => !in_array($guru->id, $declined, true))
            : null;
        $toBackup     = $backup !== null;

        $task->update(['data' => [...$task->data, 'declined_by' => $declined]] + ($toBackup ? ['assignee_id' => $backup->id, 'assigned_at' => now()] : []));

        if ($task->conversation) {
            SendStaffMessage::run($task->conversation, $assistant, ['body' => __(':name did not want to answer this question.', ['name' => $user->contact_name ?: $user->username])]);
        }

        if ($holderKeeps) {
            return 'kept';
        }

        if ($toBackup) {
            AlertShoppingAssistantTeamQuestion::dispatch($task, $backup);

            return 'to_backup';
        }

        $this->close($task, $user);

        return 'closed';
    }

    /**
     * Nobody left who wants to answer: the task is closed and the customer, who was promised an
     * answer, is told plainly to ask customer services, by email too when they stopped waiting.
     */
    public function close(StaffTask $task, User $user, ?string $note = null): void
    {
        $awaited = StoreShoppingAssistantTeamQuestion::isAwaited($task);
        $task->update(['data' => [...$task->data, 'answered_at' => now()->toIso8601String(), 'unanswered' => true]]);

        $conversation = ShoppingAssistantConversation::with('customer.shop', 'website')->find(data_get($task->data, 'conversation_id'));
        if ($conversation?->customer && !$conversation->wiped_at) {
            $message = __('Our team could not answer this question. Please contact our customer service team.', [], $conversation->language_code);
            $conversation->messages()->create([
                'role'  => ShoppingAssistantMessageRoleEnum::ASSISTANT,
                'body'  => $message,
                'model' => 'team',
                'flags' => ['team_unanswered'],
            ]);
            $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

            if (!$awaited) {
                [, $log] = FollowUpShoppingAssistantRestockQuestion::make()->deliver($conversation, $message, $message, $message, __('Nobody in our team could answer a question this customer asked the AI shopping assistant (:reference). Please tell the customer and help them if you can.', ['reference' => $task->reference]), $task, GetAikuAssistant::run($task->group_id));
                if ($task->conversation) {
                    SendStaffMessage::run($task->conversation, GetAikuAssistant::run($task->group_id), ['body' => $log]);
                }
            }
        }

        UpdateStaffTask::run($task, $user, ['status' => StaffTaskStatusEnum::CANCELLED->value, 'note' => $note ?? __('Nobody wanted to answer: the customer was asked to contact customer services.')]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return StoreShoppingAssistantTeamQuestion::canAnswer($request->route('staffTask'), $request->user());
    }

    public function asController(StaffTask $staffTask, ActionRequest $request): JsonResponse
    {
        return response()->json(['result' => $this->handle($staffTask, $request->user())]);
    }
}
