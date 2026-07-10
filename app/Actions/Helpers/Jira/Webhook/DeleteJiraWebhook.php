<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Jira\Webhook;

use App\Actions\GrpAction;
use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class DeleteJiraWebhook extends GrpAction
{
    use WithJiraApiRequest;
    use WithActionUpdate;
    use AsCommand;

    public string $commandSignature = 'jira:delete-webhook {group : The group slug} {webhookIds* : The webhook ids to delete}';

    /**
     * @param  array<int, int>  $webhookIds
     *
     * @return array<string, mixed>|null
     */
    public function handle(Group $group, array $webhookIds): ?array
    {
        $this->setJiraGroup($group);

        $response = $this->deleteJiraWebhooks($webhookIds);

        $groupSettings = $group->settings;
        data_forget($groupSettings, 'jira.webhooks');
        $this->update($group, ['settings' => $groupSettings]);

        return $response;
    }

    /**
     * @param  array<int, int>  $webhookIds
     *
     * @return array<string, mixed>|null
     */
    public function action(Group $group, array $webhookIds): ?array
    {
        $this->asAction = true;
        $this->initialisation($group, []);

        return $this->handle($group, $webhookIds);
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
        $group      = Group::where('slug', $command->argument('group'))->firstOrFail();
        $webhookIds = array_map('intval', Arr::wrap($command->argument('webhookIds')));

        $command->line(json_encode($this->action($group, $webhookIds), JSON_PRETTY_PRINT));

        return 0;
    }
}
