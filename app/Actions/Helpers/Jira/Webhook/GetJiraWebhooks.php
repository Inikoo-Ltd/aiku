<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Jira\Webhook;

use App\Actions\GrpAction;
use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class GetJiraWebhooks extends GrpAction
{
    use WithJiraApiRequest;
    use AsCommand;

    public string $commandSignature = 'jira:get-webhooks {group : The group slug}';

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Group $group): ?array
    {
        $this->setJiraGroup($group);

        return $this->getJiraWebhooks();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function action(Group $group): ?array
    {
        $this->asAction = true;
        $this->initialisation($group, []);

        return $this->handle($group);
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

        $command->line(json_encode($this->action($group), JSON_PRETTY_PRINT));

        return 0;
    }
}
