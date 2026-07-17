<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira\Concerns;

use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

trait WithChatJiraContext
{
    protected function currentChatAgent(): ?ChatAgent
    {
        return Auth::user()?->chatAgent;
    }

    protected function resolveJiraGroup(ChatSession $chatSession): ?Group
    {
        return $chatSession->shop?->group;
    }

    protected function jiraIsConfigured(?Group $group): bool
    {
        if (!$group) {
            return false;
        }

        return filled(Arr::get($group->settings, 'jira.base_url'))
            && filled(Arr::get($group->settings, 'jira.email'))
            && filled(Arr::get($group->settings, 'jira.api_token'));
    }

    protected function jiraBrowseUrl(?Group $group, ?string $issueKey): ?string
    {
        $baseUrl = rtrim((string) Arr::get($group?->settings, 'jira.base_url'), '/');

        if ($baseUrl === '' || blank($issueKey)) {
            return null;
        }

        return $baseUrl.'/browse/'.$issueKey;
    }
}
