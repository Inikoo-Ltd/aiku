<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket\Concerns;

use App\Models\SysAdmin\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait WithJiraApi
{
    private ?array $jiraCredentials = null;

    protected function jira(): PendingRequest
    {
        $this->jiraCredentials ??= User::whereRaw("settings->'jira'->>'api_token' is not null")->first()?->settings['jira']
            ?? throw new RuntimeException('No user has Jira credentials in settings.jira');

        return Http::baseUrl(rtrim($this->jiraCredentials['base_url'], '/'))
            ->withBasicAuth($this->jiraCredentials['email'], $this->jiraCredentials['api_token'])
            ->timeout(120)
            ->retry(3, 5000, throw: false);
    }
}
