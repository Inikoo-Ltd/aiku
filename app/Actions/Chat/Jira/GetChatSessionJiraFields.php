<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira;

use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatSessionJiraFields
{
    use AsAction;
    use WithChatJiraContext;
    use WithJiraApiRequest;

    private const HANDLED_SYSTEM_FIELDS = [
        'summary',
        'issuetype',
        'project',
        'description',
        'reporter',
        'attachment',
        'priority',
        'labels',
    ];

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, string $project, string $issueType): JsonResponse
    {
        $agent = $this->currentChatAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can access Jira',
            ], 403);
        }

        $credentials = $this->agentJiraCredentials($agent);

        if (!$this->jiraCredentialsConfigured($credentials)) {
            return response()->json([
                'success'    => true,
                'configured' => false,
                'data'       => [],
            ]);
        }

        try {
            $response = $this->setJiraCredentials($credentials)->getJiraCreateFields($project, $issueType);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $rawFields = Arr::get($response, 'values', Arr::get($response, 'fields', []));

        $fields = [];

        foreach ($rawFields as $field) {
            if (!Arr::get($field, 'required')) {
                continue;
            }

            $system = Arr::get($field, 'schema.system');
            if ($system !== null && in_array($system, self::HANDLED_SYSTEM_FIELDS, true)) {
                continue;
            }

            if (Arr::get($field, 'hasDefaultValue')) {
                continue;
            }

            $options = array_map(static function (array $option): array {
                return [
                    'id'    => (string) (Arr::get($option, 'id') ?? Arr::get($option, 'value')),
                    'label' => (string) (Arr::get($option, 'value') ?? Arr::get($option, 'name') ?? Arr::get($option, 'id')),
                ];
            }, Arr::get($field, 'allowedValues', []));

            $fields[] = [
                'id'       => Arr::get($field, 'fieldId', Arr::get($field, 'key')),
                'name'     => Arr::get($field, 'name'),
                'type'     => Arr::get($field, 'schema.type', 'string'),
                'required' => true,
                'options'  => $options,
            ];
        }

        return response()->json([
            'success'    => true,
            'configured' => true,
            'data'       => $fields,
        ]);
    }
}
