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

class GetChatSessionJiraLabels
{
    use AsAction;
    use WithChatJiraContext;
    use WithJiraApiRequest;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
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
            $response = $this->setJiraCredentials($credentials)->getJiraLabels(['maxResults' => 500]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'success'    => true,
            'configured' => true,
            'data'       => array_values(Arr::get($response, 'values', [])),
        ]);
    }
}
