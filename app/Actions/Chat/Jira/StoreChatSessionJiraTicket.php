<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira;

use App\Actions\Chat\ChatSession\StoreChatEvent;
use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use App\Actions\Helpers\Jira\CreateJiraTicket;
use App\Actions\Helpers\Jira\Traits\WithJiraApiRequest;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreChatSessionJiraTicket
{
    use AsAction;
    use WithChatJiraContext;
    use WithJiraApiRequest;

    /**
     * @param  array<string, mixed>  $modelData
     *
     * @return array<string, mixed>
     */
    public function handle(ChatSession $chatSession, Group $group, ChatAgent $agent, array $modelData): array
    {
        $response = CreateJiraTicket::make()->action($group, [
            'project_key' => Arr::get($modelData, 'project_key'),
            'summary'     => Arr::get($modelData, 'summary'),
            'issue_type'  => Arr::get($modelData, 'issue_type', 'Task'),
            'description' => $this->composeDescription($modelData),
            'priority'    => Arr::get($modelData, 'priority'),
            'labels'      => Arr::get($modelData, 'labels', []),
        ]);

        if (!is_array($response) || Arr::get($response, 'error')) {
            $messages = Arr::get($response, 'messages', ['Failed to create Jira ticket']);

            throw new Exception(implode(' ', (array) $messages));
        }

        $issueKey = Arr::get($response, 'key');
        $browseUrl = $this->jiraBrowseUrl($group, $issueKey);

        $attachments = Arr::get($modelData, 'attachments', []);
        $attachmentCount = 0;

        if (is_array($attachments) && $attachments !== []) {
            $this->setJiraGroup($group)->attachJiraIssueFiles($issueKey, $attachments);
            $attachmentCount = count($attachments);
        }

        $labels = Arr::get($modelData, 'labels', []);

        $ticket = [
            'id'               => Arr::get($response, 'id'),
            'key'              => $issueKey,
            'url'              => $browseUrl,
            'summary'          => Arr::get($modelData, 'summary'),
            'issue_type'       => Arr::get($modelData, 'issue_type', 'Task'),
            'project_key'      => Arr::get($modelData, 'project_key'),
            'priority'         => Arr::get($modelData, 'priority'),
            'priority_name'    => Arr::get($modelData, 'priority_name'),
            'labels'           => is_array($labels) ? array_values($labels) : [],
            'reference_url'    => Arr::get($modelData, 'reference_url'),
            'has_attachment'   => $attachmentCount > 0,
            'attachment_count' => $attachmentCount,
        ];

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::JIRA_TICKET,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $agent->id,
            payload: [
                ...$ticket,
                'created_by_agent_id'   => $agent->id,
                'created_by_agent_name' => $agent->user?->contact_name,
                'created_at'            => now()->toISOString(),
            ]
        );

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $modelData
     */
    protected function composeDescription(array $modelData): ?string
    {
        $description = trim((string) Arr::get($modelData, 'description', ''));
        $referenceUrl = trim((string) Arr::get($modelData, 'reference_url', ''));

        if ($referenceUrl !== '') {
            $description = $description === ''
                ? 'Reference: '.$referenceUrl
                : $description."\n\nReference: ".$referenceUrl;
        }

        return $description === '' ? null : $description;
    }

    public function rules(): array
    {
        return [
            'project_key'   => ['required', 'string'],
            'summary'       => ['required', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'issue_type'    => ['sometimes', 'string'],
            'priority'      => ['sometimes', 'nullable', 'string'],
            'priority_name' => ['sometimes', 'nullable', 'string'],
            'labels'        => ['sometimes', 'array'],
            'labels.*'      => ['string'],
            'reference_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'attachments'   => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, Request $request): JsonResponse
    {
        $agent = $this->currentChatAgent();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Only authenticated agents can create Jira tickets',
            ], 403);
        }

        $group = $this->resolveJiraGroup($chatSession);

        if (!$this->jiraIsConfigured($group)) {
            return response()->json([
                'success' => false,
                'message' => 'Jira is not configured for this group',
            ], 422);
        }

        $validated = $request->validate($this->rules());

        $validated['attachments'] = collect($request->file('attachments') ?? [])
            ->map(static fn ($file): array => [
                'contents' => (string) $file->get(),
                'name'     => $file->getClientOriginalName(),
            ])
            ->filter(static fn (array $file): bool => $file['contents'] !== '')
            ->values()
            ->all();

        try {
            $ticket = $this->handle($chatSession, $group, $agent, $validated);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jira ticket created successfully',
            'data'    => $ticket,
        ]);
    }
}
