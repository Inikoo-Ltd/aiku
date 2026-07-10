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

class GetJiraProjects extends GrpAction
{
    use WithJiraApiRequest;
    use AsCommand;

    public string $commandSignature = 'jira:projects {group : The group slug}';

    /**
     * @return array<int, array{id: string, key: string, name: string}>
     */
    public function handle(Group $group): array
    {
        $this->setJiraGroup($group);

        $response = $this->getJiraProjects(['maxResults' => 100]);

        return array_map(static function (array $project): array {
            return [
                'id'   => Arr::get($project, 'id'),
                'key'  => Arr::get($project, 'key'),
                'name' => Arr::get($project, 'name'),
            ];
        }, Arr::get($response, 'values', []));
    }

    /**
     * @return array<int, array{id: string, key: string, name: string}>
     */
    public function action(Group $group): array
    {
        $this->asAction = true;
        $this->initialisation($group, []);

        return $this->handle($group);
    }

    /**
     * @return array<int, array{id: string, key: string, name: string}>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation(group(), $request);

        return $this->handle($this->group);
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

        $command->table(['Id', 'Key', 'Name'], $this->action($group));

        return 0;
    }
}
