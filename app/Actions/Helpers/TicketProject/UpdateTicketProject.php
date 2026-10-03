<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use App\Models\Helpers\TicketProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketProject extends OrgAction
{
    public function handle(TicketProject $project, array $modelData): TicketProject
    {
        if (Arr::exists($modelData, 'member_ids')) {
            $project->members()->sync(Arr::pull($modelData, 'member_ids') ?? []);
        }

        if (Arr::exists($modelData, 'milestones')) {
            $this->syncMilestones($project, Arr::pull($modelData, 'milestones') ?? []);
        }

        $project->update($modelData);

        return $project;
    }

    /**
     * The whole list comes back from the page in its order: rows it no longer has are removed
     * (their tickets and tasks stay in the project, just without a milestone), the rest are
     * updated or created, and done_at keeps its first stamp while the milestone stays ticked.
     *
     * @param array<int, array{id?: int|null, name: string, description?: string|null, start_date?: string|null, due_date?: string|null, done?: bool}> $milestones
     */
    private function syncMilestones(TicketProject $project, array $milestones): void
    {
        DB::transaction(function () use ($project, $milestones) {
            $existing = $project->milestones()->get()->keyBy('id');
            $keptIds  = collect($milestones)->pluck('id')->filter()->all();

            $project->milestones()->whereNotIn('id', $keptIds)->delete();

            foreach (array_values($milestones) as $position => $milestone) {
                $current = isset($milestone['id']) ? $existing->get($milestone['id']) : null;
                $isDone  = (bool) ($milestone['done'] ?? false);

                $attributes = [
                    'name'        => trim($milestone['name']),
                    'description' => $milestone['description'] ?? $current?->description,
                    'start_date'  => $milestone['start_date'] ?? null,
                    'due_date'    => $milestone['due_date'] ?? null,
                    'done_at'     => $isDone ? ($current?->done_at ?? now()) : null,
                    'position'    => $position,
                ];

                $current ? $current->update($attributes) : $project->milestones()->create($attributes);
            }
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        $project = $request->route('ticketProject');

        return $this->asAction || ($project->group_id === $request->user()->group_id && $project->canBeEditedBy($request->user()));
    }

    public function rules(): array
    {
        return [
            'name'                    => ['sometimes', 'string', 'max:255'],
            'description'             => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status'                  => ['sometimes', Rule::enum(TicketProjectStatusEnum::class)],
            'start_date'              => ['sometimes', 'date'],
            'target_date'             => ['sometimes', 'nullable', 'date', 'after_or_equal:'.($this->get('start_date') ?? request()->route('ticketProject')?->start_date?->toDateString() ?? '1900-01-01')],
            'owner_id'                => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'member_ids'              => ['sometimes', 'nullable', 'array'],
            'member_ids.*'            => [Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'milestones'              => ['sometimes', 'nullable', 'array', 'max:100'],
            'milestones.*.id'          => ['nullable', 'integer'],
            'milestones.*.name'        => ['required', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string', 'max:5000'],
            'milestones.*.start_date'  => ['nullable', 'date'],
            'milestones.*.due_date'    => ['nullable', 'date'],
            'milestones.*.done'        => ['sometimes', 'boolean'],
        ];
    }

    public function action(TicketProject $project, array $modelData): TicketProject
    {
        $this->asAction = true;
        $this->initialisationFromGroup($project->group, $modelData);

        return $this->handle($project, $this->validatedData);
    }

    public function asController(TicketProject $ticketProject, ActionRequest $request): TicketProject
    {
        $this->initialisationFromGroup($ticketProject->group, $request);

        return $this->handle($ticketProject, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
