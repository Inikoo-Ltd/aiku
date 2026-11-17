<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketProject;
use App\Models\SysAdmin\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Lorisleiva\Actions\ActionRequest;

class StoreTicketProject extends OrgAction
{
    public function handle(Group $group, array $modelData): TicketProject
    {
        $memberIds = Arr::pull($modelData, 'member_ids', []);

        $project = TicketProject::create([...$modelData, 'group_id' => $group->id]);
        $project->members()->sync($memberIds);

        return $project;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || TicketProject::canBeCreatedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:20000'],
            'start_date'   => ['required', 'date'],
            'target_date'  => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'owner_id'     => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'member_ids'   => ['sometimes', 'array'],
            'member_ids.*' => [Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'parent_id'    => ['sometimes', 'nullable', 'integer', self::parentRule($this->group->id)],
        ];
    }

    /**
     * Sub-projects go one level deep: a parent is a top-level project of the same group.
     */
    public static function parentRule(int $groupId): Exists
    {
        return Rule::exists('ticket_projects', 'id')->where('group_id', $groupId)->whereNull('parent_id')->whereNull('deleted_at');
    }

    public function action(Group $group, array $modelData): TicketProject
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    public function asController(ActionRequest $request): TicketProject
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    public function htmlResponse(TicketProject $project): RedirectResponse
    {
        return redirect()->route('grp.projects.show', $project->slug);
    }
}
