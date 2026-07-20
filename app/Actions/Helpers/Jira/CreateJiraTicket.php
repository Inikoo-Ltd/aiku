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

class CreateJiraTicket extends GrpAction
{
    use WithJiraApiRequest;
    use AsCommand;

    public string $commandSignature = 'jira:create-ticket {group : The group slug} {project : The project key} {summary : The ticket summary} {--issue-type=Task : The issue type name} {--description= : The ticket description}';

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Group $group, array $modelData): ?array
    {
        $this->setJiraGroup($group);

        $fields = [
            'project'   => ['key' => Arr::get($modelData, 'project_key')],
            'summary'   => Arr::get($modelData, 'summary'),
            'issuetype' => ['name' => Arr::get($modelData, 'issue_type', 'Task')],
        ];

        if (filled(Arr::get($modelData, 'description'))) {
            $fields['description'] = $this->textToAtlassianDocument(Arr::get($modelData, 'description'));
        }

        if (filled(Arr::get($modelData, 'priority'))) {
            $fields['priority'] = ['id' => (string) Arr::get($modelData, 'priority')];
        }

        $labels = Arr::get($modelData, 'labels', []);
        if (is_array($labels) && $labels !== []) {
            $fields['labels'] = array_values($labels);
        }

        return $this->createJiraIssue($fields);
    }

    /**
     * @return array<string, mixed>
     */
    protected function textToAtlassianDocument(string $text): array
    {
        return [
            'type'    => 'doc',
            'version' => 1,
            'content' => [
                [
                    'type'    => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'project_key' => ['required', 'string'],
            'summary'     => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'issue_type'  => ['sometimes', 'string'],
            'priority'    => ['sometimes', 'nullable', 'string'],
            'labels'      => ['sometimes', 'array'],
            'labels.*'    => ['string'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function action(Group $group, array $modelData): ?array
    {
        $this->asAction = true;
        $this->initialisation($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function asController(ActionRequest $request): ?array
    {
        $this->initialisation(group(), $request);

        return $this->handle($this->group, $this->validatedData);
    }

    public function asCommand($command): int
    {
        $group = Group::where('slug', $command->argument('group'))->firstOrFail();

        $response = $this->action($group, [
            'project_key' => $command->argument('project'),
            'summary'     => $command->argument('summary'),
            'issue_type'  => $command->option('issue-type'),
            'description' => $command->option('description'),
        ]);

        $command->info('Jira ticket created: '.Arr::get($response, 'key'));
        $command->line(json_encode($response, JSON_PRETTY_PRINT));

        return 0;
    }
}
