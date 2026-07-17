<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira;

use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use App\Actions\Helpers\Jira\GetJiraIssueTypes;
use App\Models\Chat\ChatSession;
use Exception;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatSessionJiraIssueTypes
{
    use AsAction;
    use WithChatJiraContext;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, string $project, ActionRequest $request): JsonResponse
    {
        if (!$this->currentChatAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can access Jira',
            ], 403);
        }

        $group = $this->resolveJiraGroup($chatSession);

        if (!$this->jiraIsConfigured($group)) {
            return response()->json([
                'success'    => true,
                'configured' => false,
                'data'       => [],
            ]);
        }

        try {
            $issueTypes = GetJiraIssueTypes::make()->action($group, $project);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $issueTypes = array_values(array_filter($issueTypes, static function (array $issueType): bool {
            return !($issueType['subtask'] ?? false);
        }));

        return response()->json([
            'success'    => true,
            'configured' => true,
            'data'       => $issueTypes,
        ]);
    }
}
