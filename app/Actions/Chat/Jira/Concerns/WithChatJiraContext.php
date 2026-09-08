<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira\Concerns;

use App\Models\Chat\ChatAgent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

trait WithChatJiraContext
{
    protected function currentChatAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }

    /**
     * @return array{base_url: ?string, email: ?string, api_token: ?string}
     */
    protected function agentJiraCredentials(?ChatAgent $agent): array
    {
        $jira = Arr::get($agent?->user?->settings ?? [], 'jira', []);

        return [
            'base_url'  => rtrim((string) Arr::get($jira, 'base_url', ''), '/'),
            'email'     => Arr::get($jira, 'email'),
            'api_token' => Arr::get($jira, 'api_token'),
        ];
    }

    /**
     * @param  array{base_url: ?string, email: ?string, api_token: ?string}  $credentials
     */
    protected function jiraCredentialsConfigured(array $credentials): bool
    {
        return filled(Arr::get($credentials, 'base_url'))
            && filled(Arr::get($credentials, 'email'))
            && filled(Arr::get($credentials, 'api_token'));
    }

    /**
     * @param  array{base_url: ?string, email: ?string, api_token: ?string}  $credentials
     */
    protected function jiraBrowseUrl(array $credentials, ?string $issueKey): ?string
    {
        $baseUrl = rtrim((string) Arr::get($credentials, 'base_url', ''), '/');

        if ($baseUrl === '' || blank($issueKey)) {
            return null;
        }

        return $baseUrl.'/browse/'.$issueKey;
    }
}
