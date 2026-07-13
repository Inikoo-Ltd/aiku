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

class RegisterJiraWebhook extends GrpAction
{
    use WithJiraApiRequest;
    use WithActionUpdate;
    use AsCommand;

    public string $commandSignature = 'jira:register-webhook {group : The group slug} {--jql=* : JQL filters to scope the webhook}';

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Group $group, array $modelData = []): ?array
    {
        $this->setJiraGroup($group);

        $jqlFilters = Arr::get($modelData, 'jql', ['project is not EMPTY']);
        $events     = Arr::get($modelData, 'events', ['jira:issue_updated']);

        $callbackUrl = route('webhooks.jira.updated', ['group' => $group->id]);

        $response = $this->registerJiraWebhook($callbackUrl, $jqlFilters, $events);

        $groupSettings = $group->settings;
        data_set($groupSettings, 'jira.webhooks', Arr::get($response, 'webhookRegistrationResult', $response));
        $this->update($group, ['settings' => $groupSettings]);

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function action(Group $group, array $modelData = []): ?array
    {
        $this->asAction = true;
        $this->initialisation($group, $modelData);

        return $this->handle($group, $modelData);
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

        $response = $this->action($group, [
            'jql' => $command->option('jql') ?: ['project is not EMPTY'],
        ]);

        $command->info('Jira webhook registered.');
        $command->line(json_encode($response, JSON_PRETTY_PRINT));

        return 0;
    }
}
