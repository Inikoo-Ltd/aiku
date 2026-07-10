<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Jira;

use App\Actions\GrpAction;
use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class GetJiraIssueTypes extends GrpAction
{
    use WithJiraApiRequest;
    use AsCommand;

    public string $commandSignature = 'jira:issue-types {group : The group slug} {project : The project id or key}';

    /**
     * @return array<int, array{id: string, name: string, description: ?string, subtask: bool}>
     */
    public function handle(Group $group, string $projectIdOrKey): array
    {
        $this->setJiraGroup($group);

        $response = $this->getJiraProjectIssueTypes($projectIdOrKey);

        return array_map(static function (array $issueType): array {
            return [
                'id'          => Arr::get($issueType, 'id'),
                'name'        => Arr::get($issueType, 'name'),
                'description' => Arr::get($issueType, 'description'),
                'subtask'     => (bool) Arr::get($issueType, 'subtask', false),
            ];
        }, Arr::get($response, 'issueTypes', []));
    }

    /**
     * @return array<int, array{id: string, name: string, description: ?string, subtask: bool}>
     */
    public function action(Group $group, string $projectIdOrKey): array
    {
        $this->asAction = true;
        $this->initialisation($group, []);

        return $this->handle($group, $projectIdOrKey);
    }

    /**
     * @return array<int, array{id: string, name: string, description: ?string, subtask: bool}>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation(group(), $request);

        return $this->handle($this->group, $request->route('project'));
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    public function asCommand($command): int
    {
        $group = Group::where('slug', $command->argument('group'))->firstOrFail();

        $issueTypes = array_map(static function (array $issueType): array {
            return Arr::only($issueType, ['id', 'name', 'subtask']);
        }, $this->action($group, $command->argument('project')));

        $command->table(['Id', 'Name', 'Subtask'], $issueTypes);

        return 0;
    }
}
