<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026-10h-19m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\Helpers\Ticket\UI\ShowTicketsDashboard;
use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class GetTicketQaQueue extends OrgAction
{
    public const array CHECKERS = ['all', 'anyone', 'me'];

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $user !== null && (Ticket::canBeManagedBy($user) || Ticket::canCheckQa($user));
    }

    public function rules(): array
    {
        return [
            'checker' => ['sometimes', 'string', Rule::in(self::CHECKERS)],
        ];
    }

    public function handle(Group $group, User $user, string $checker = 'all'): array
    {
        return ShowTicketsDashboard::make()->qaQueue($group, $user, $checker);
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $request->user(), $this->validatedData['checker'] ?? 'all');
    }
}
