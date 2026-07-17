<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Jira\Traits;

use App\Models\SysAdmin\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WithJiraApiRequest
{
    protected ?Group $jiraGroup = null;

    /**
     * @var array{base_url: ?string, email: ?string, api_token: ?string}|null
     */
    protected ?array $jiraCredentialsOverride = null;

    protected string $jiraApiVersion = '3';

    public int $jiraTimeOut = 30;

    public function setJiraGroup(Group $group): static
    {
        $this->jiraGroup = $group;

        return $this;
    }

    /**
     * @param  array{base_url?: ?string, email?: ?string, api_token?: ?string}  $credentials
     */
    public function setJiraCredentials(array $credentials): static
    {
        $this->jiraCredentialsOverride = [
            'base_url'  => rtrim((string) Arr::get($credentials, 'base_url'), '/'),
            'email'     => Arr::get($credentials, 'email'),
            'api_token' => Arr::get($credentials, 'api_token'),
        ];

        return $this;
    }

    public function setJiraTimeout(int $timeOut): static
    {
        $this->jiraTimeOut = $timeOut;

        return $this;
    }

    protected function getJiraGroup(): Group
    {
        if (!$this->jiraGroup) {
            $this->jiraGroup = $this->group;
        }

        return $this->jiraGroup;
    }

    /**
     * @return array{base_url: ?string, email: ?string, api_token: ?string}
     */
    protected function getJiraCredentials(): array
    {
        if ($this->jiraCredentialsOverride !== null) {
            return $this->jiraCredentialsOverride;
        }

        $settings = $this->getJiraGroup()->settings;

        return [
            'base_url'  => rtrim((string) Arr::get($settings, 'jira.base_url'), '/'),
            'email'     => Arr::get($settings, 'jira.email'),
            'api_token' => Arr::get($settings, 'jira.api_token'),
        ];
    }

    protected function hasJiraCredentials(): bool
    {
        $credentials = $this->getJiraCredentials();

        return !empty($credentials['base_url'])
            && !empty($credentials['email'])
            && !empty($credentials['api_token']);
    }

    protected function getJiraApiUrl(): string
    {
        return $this->getJiraCredentials()['base_url'].'/rest/api/'.$this->jiraApiVersion;
    }

    protected function jiraClient(): PendingRequest
    {
        $credentials = $this->getJiraCredentials();

        return Http::timeout($this->jiraTimeOut)
            ->connectTimeout($this->jiraTimeOut)
            ->withBasicAuth($credentials['email'], $credentials['api_token'])
            ->acceptJson()
            ->asJson();
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @return array<string, mixed>|null
     */
    protected function makeJiraRequest(string $method, string $endpoint, array $params = []): ?array
    {
        $url = $this->getJiraApiUrl().'/'.ltrim($endpoint, '/');

        try {
            $response = match (strtoupper($method)) {
                'GET'    => $this->jiraClient()->get($url, $params),
                'POST'   => $this->jiraClient()->post($url, $params),
                'PUT'    => $this->jiraClient()->put($url, $params),
                'DELETE' => $this->jiraClient()->delete($url, $params),
                default  => throw new \InvalidArgumentException("Unsupported HTTP method: $method"),
            };

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'error'    => true,
                'status'   => $response->status(),
                'messages' => Arr::get($response->json(), 'errorMessages', [$response->body()]),
                'errors'   => Arr::get($response->json(), 'errors', []),
            ];
        } catch (ConnectionException $e) {
            Log::error('Jira API Connection Error', [
                'url'    => $url,
                'method' => $method,
                'error'  => $e->getMessage(),
            ]);

            return [
                'error'    => true,
                'messages' => ['Jira API Connection Error: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @return array<string, mixed>|null
     */
    public function getJiraProjects(array $params = []): ?array
    {
        return $this->makeJiraRequest('GET', 'project/search', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJiraProjectIssueTypes(string $projectIdOrKey): ?array
    {
        return $this->makeJiraRequest('GET', "issue/createmeta/$projectIdOrKey/issuetypes");
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @return array<string, mixed>|null
     */
    public function getJiraPriorities(array $params = []): ?array
    {
        return $this->makeJiraRequest('GET', 'priority/search', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @return array<string, mixed>|null
     */
    public function getJiraLabels(array $params = []): ?array
    {
        return $this->makeJiraRequest('GET', 'label', $params);
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @return array<string, mixed>|null
     */
    public function createJiraIssue(array $fields): ?array
    {
        return $this->makeJiraRequest('POST', 'issue', ['fields' => $fields]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attachJiraIssueFile(string $issueIdOrKey, string $contents, string $fileName): ?array
    {
        return $this->attachJiraIssueFiles($issueIdOrKey, [
            ['contents' => $contents, 'name' => $fileName],
        ]);
    }

    /**
     * @param  array<int, array{contents: string, name: string}>  $files
     *
     * @return array<string, mixed>|null
     */
    public function attachJiraIssueFiles(string $issueIdOrKey, array $files): ?array
    {
        if ($files === []) {
            return [];
        }

        $credentials = $this->getJiraCredentials();
        $url = $this->getJiraApiUrl()."/issue/$issueIdOrKey/attachments";

        try {
            $request = Http::timeout($this->jiraTimeOut)
                ->connectTimeout($this->jiraTimeOut)
                ->withBasicAuth($credentials['email'], $credentials['api_token'])
                ->withHeaders(['X-Atlassian-Token' => 'no-check'])
                ->acceptJson();

            foreach ($files as $file) {
                $request = $request->attach('file', Arr::get($file, 'contents'), Arr::get($file, 'name', 'attachment'));
            }

            $response = $request->post($url);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [
                'error'    => true,
                'status'   => $response->status(),
                'messages' => Arr::get($response->json(), 'errorMessages', [$response->body()]),
            ];
        } catch (ConnectionException $e) {
            Log::error('Jira API Attachment Error', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'error'    => true,
                'messages' => ['Jira API Attachment Error: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJiraIssue(string $issueIdOrKey): ?array
    {
        return $this->makeJiraRequest('GET', "issue/$issueIdOrKey");
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @return array<string, mixed>|null
     */
    public function updateJiraIssue(string $issueIdOrKey, array $fields): ?array
    {
        return $this->makeJiraRequest('PUT', "issue/$issueIdOrKey", ['fields' => $fields]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJiraIssueTransitions(string $issueIdOrKey): ?array
    {
        return $this->makeJiraRequest('GET', "issue/$issueIdOrKey/transitions");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function transitionJiraIssue(string $issueIdOrKey, string $transitionId): ?array
    {
        return $this->makeJiraRequest('POST', "issue/$issueIdOrKey/transitions", [
            'transition' => ['id' => $transitionId],
        ]);
    }

    /**
     * @param  array<int, string>  $events
     * @param  array<int, string>  $jqlFilters
     *
     * @return array<string, mixed>|null
     */
    public function registerJiraWebhook(string $callbackUrl, array $jqlFilters, array $events = ['jira:issue_updated']): ?array
    {
        $webhooks = array_map(static function (string $jqlFilter) use ($events): array {
            return [
                'events'            => $events,
                'jqlFilter'         => $jqlFilter,
                'fieldIdsFilter'    => [],
                'issuePropertyKeys' => [],
            ];
        }, $jqlFilters);

        return $this->makeJiraRequest('POST', 'webhook', [
            'url'      => $callbackUrl,
            'webhooks' => $webhooks,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJiraWebhooks(): ?array
    {
        return $this->makeJiraRequest('GET', 'webhook');
    }

    /**
     * @param  array<int, int>  $webhookIds
     *
     * @return array<string, mixed>|null
     */
    public function deleteJiraWebhooks(array $webhookIds): ?array
    {
        return $this->makeJiraRequest('DELETE', 'webhook', ['webhookIds' => $webhookIds]);
    }

    public function checkJiraConnection(): bool
    {
        if (!$this->hasJiraCredentials()) {
            return false;
        }

        $response = $this->makeJiraRequest('GET', 'myself');

        return is_array($response) && empty($response['error']) && !empty($response['accountId']);
    }
}
