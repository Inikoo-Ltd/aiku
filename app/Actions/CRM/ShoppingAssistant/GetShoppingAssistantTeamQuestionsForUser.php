<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The question pop-ups this person still has to answer, read when aiku opens so a reload never
 * loses one, the colleagues a question can be passed to, and whether this guru still has to see
 * the welcome that tells them why they were chosen (shown once, before their first question).
 */
class GetShoppingAssistantTeamQuestionsForUser
{
    use AsAction;

    /**
     * @return array{questions: array<int, array<string, string>>, colleagues: array<int, array{id: int, name: string}>, welcome: bool}
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
            'welcome'    => !data_get($user->settings, self::WELCOME_SEEN) && self::isGuru($user),
        ];
    }

    public const string WELCOME_SEEN = 'shopping_assistant_guru_welcome_seen_at';

    public static function isGuru(User $user): bool
    {
        $id = (string) $user->id;

        return Shop::where('group_id', $user->group_id)->whereRaw("settings->'assistant_questions'->>'user_id' = ?", [$id])->exists()
            || Organisation::where('group_id', $user->group_id)->where(fn ($query) => $query
                ->whereRaw("settings->'assistant_questions'->>'user_id' = ?", [$id])
                ->orWhereRaw("settings->'assistant_questions'->>'backup_user_id' = ?", [$id]))->exists();
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->user(), $request->boolean('colleagues'));
    }
}
