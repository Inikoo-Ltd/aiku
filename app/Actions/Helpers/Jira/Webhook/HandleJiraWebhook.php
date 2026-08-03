<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Jira\Webhook;

use App\Models\SysAdmin\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class HandleJiraWebhook
{
    use AsAction;
    use WithAttributes;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Group $group, array $payload): void
    {
        $webhookEvent = Arr::get($payload, 'webhookEvent');
        $issue        = Arr::get($payload, 'issue', []);

        Log::info('Jira webhook received', [
            'group_id'      => $group->id,
            'webhook_event' => $webhookEvent,
            'issue_key'     => Arr::get($issue, 'key'),
            'issue_id'      => Arr::get($issue, 'id'),
            'status'        => Arr::get($issue, 'fields.status.name'),
        ]);
    }

    public function asController(Group $group, ActionRequest $request): JsonResponse
    {
        $this->handle($group, $request->all());

        return response()->json(['received' => true]);
    }
}
