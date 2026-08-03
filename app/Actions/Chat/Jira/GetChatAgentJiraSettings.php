<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira;

use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatAgentJiraSettings
{
    use AsAction;
    use WithChatJiraContext;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation = null): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $credentials = $this->agentJiraCredentials($user->chatAgent);

        return response()->json([
            'success' => true,
            'data'    => [
                'configured' => $this->jiraCredentialsConfigured($credentials),
                'base_url'   => $credentials['base_url'] ?: null,
                'email'      => $credentials['email'],
                'has_token'  => filled($credentials['api_token']),
            ],
        ]);
    }
}
