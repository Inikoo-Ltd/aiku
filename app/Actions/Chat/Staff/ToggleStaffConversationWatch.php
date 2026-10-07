<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Chat\Staff;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Work chats (tasks, orders, deliveries…) stay out of a person's chat list until they choose to watch them;
 * chats with people are always listed.
 */
class ToggleStaffConversationWatch
{
    use AsAction;

    public function handle(StaffConversation $conversation, User $user, bool $isWatching): bool
    {
        $conversation->participants()->updateExistingPivot($user->id, ['is_watching' => $isWatching]);

        return $isWatching;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffConversation')->hasParticipant($request->user());
    }

    public function rules(): array
    {
        return [
            'is_watching' => ['required', 'boolean'],
        ];
    }

    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        return ['is_watching' => $this->handle($staffConversation, $request->user(), $request->boolean('is_watching'))];
    }
}
