<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The question pop-ups this person still has to answer, read when aiku opens so a reload never
 * loses one, and the colleagues a question can be passed to.
 */
class GetShoppingAssistantTeamQuestionsForUser
{
    use AsAction;

    /**
     * @return array{questions: array<int, array<string, string>>, colleagues: array<int, array{id: int, name: string}>}
     */
    public function handle(User $user, bool $withColleagues): array
    {
        return [
            'questions'  => StoreShoppingAssistantTeamQuestion::waitingFor($user)->map(fn (StaffTask $task) => StoreShoppingAssistantTeamQuestion::popup($task))->all(),
            'colleagues' => $withColleagues
                ? User::where('group_id', $user->group_id)->where('status', true)->where('id', '!=', $user->id)->orderBy('contact_name')
                    ->get(['id', 'contact_name', 'username', 'nickname'])
                    ->filter(fn (User $colleague) => StaffTask::canBeAssigned($colleague))
                    ->map(fn (User $colleague) => ['id' => $colleague->id, 'name' => $colleague->chatName()])->values()->all()
                : [],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user(), $request->boolean('colleagues'));
    }
}
