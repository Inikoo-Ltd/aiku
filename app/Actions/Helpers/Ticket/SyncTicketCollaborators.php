<?php

/*
 * Author Louis Perez
 * Created on 15-09-2026-11h-41m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class SyncTicketCollaborators extends OrgAction
{
    private ?Ticket $syncingTicket = null;

    /**
     * @param array<int, int|string> $collaboratorIds
     */
    public function handle(Ticket $ticket, array $collaboratorIds, ?User $actor = null): Ticket
    {
        $previousIds = $ticket->collaborators()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $wantedIds   = collect($collaboratorIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $ticket->assignee_id)
            ->unique()
            ->values()
            ->all();

        $addedIds   = array_values(array_diff($wantedIds, $previousIds));
        $removedIds = array_values(array_diff($previousIds, $wantedIds));

        if ($addedIds === [] && $removedIds === []) {
            return $ticket;
        }

        if ($removedIds !== []) {
            $ticket->collaborators()->detach($removedIds);
        }

        if ($addedIds !== []) {
            $ticket->collaborators()->attach(collect($addedIds)->mapWithKeys(fn (int $id) => [$id => ['added_by_id' => $actor?->id]])->all());
        }

        $this->recordHistory($ticket, $previousIds, $wantedIds);
        $ticket->touch();

        foreach (User::whereIn('id', $addedIds)->get() as $collaborator) {
            NotifyTicketUsers::make()->collaboratorAdded($ticket, $collaborator, $actor);
        }

        NotifyTicketUsers::make()->pushBadges($ticket, $actor);

        if ($removedIds !== []) {
            SendTicketBadgeUpdateToUsers::run($removedIds);
        }

        return $ticket;
    }

    /**
     * @return array<int, int>
     */
    public static function candidateIds(int $groupId): array
    {
        return GetTicketBadgeData::engineers($groupId)
            ->merge(GetTicketBadgeData::qaUsers($groupId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $previousIds
     * @param array<int, int> $wantedIds
     */
    private function recordHistory(Ticket $ticket, array $previousIds, array $wantedIds): void
    {
        $names = fn (array $ids) => User::whereIn('id', $ids)->orderBy('username')->get()
            ->map(fn (User $user) => $user->contact_name ?: $user->username)
            ->implode(', ');

        $ticket->auditEvent     = 'updated';
        $ticket->isCustomEvent  = true;
        $ticket->auditCustomOld = ['collaborators' => $names($previousIds)];
        $ticket->auditCustomNew = ['collaborators' => $names($wantedIds)];
        Event::dispatch(new AuditCustom($ticket));
        $ticket->isCustomEvent = false;
    }

    public function rules(): array
    {
        return [
            'collaborator_ids'   => ['present', 'array'],
            'collaborator_ids.*' => ['integer', Rule::in($this->syncingTicket ? self::candidateIds($this->syncingTicket->group_id) : [])],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || ($this->syncingTicket?->canManageCollaboratorsBy($request->user()) ?? false);
    }

    /**
     * @param array<int, int> $collaboratorIds
     */
    public function action(Ticket $ticket, array $collaboratorIds, ?User $actor = null): Ticket
    {
        $this->asAction      = true;
        $this->syncingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, ['collaborator_ids' => $collaboratorIds]);

        return $this->handle($ticket, $this->validatedData['collaborator_ids'] ?? [], $actor);
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->syncingTicket = $ticket;
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData['collaborator_ids'] ?? [], $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
