<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketFromChatSession
{
    use AsAction;
    use WithChatJiraContext;

    public function handle(ChatSession $chatSession, ChatAgent $agent, array $modelData): Ticket
    {
        $description = trim((string) Arr::get($modelData, 'description', ''));
        if ($referenceUrl = Arr::get($modelData, 'reference_url')) {
            $description = trim($description."\n\nReference: ".$referenceUrl);
        }

        $ticket = StoreTicket::make()->action($agent->user->group, [
            'type'            => TicketTypeEnum::HELP->value,
            'subject'         => Arr::get($modelData, 'summary'),
            'description'     => $description ?: null,
            'priority'        => Arr::get($modelData, 'priority', ChatPriorityEnum::NORMAL->value),
            'organisation_id' => $chatSession->shop?->organisation_id,
            'shop_id'         => $chatSession->shop_id,
            'customer_id'     => $chatSession->webUser?->customer_id,
            'reporter_type'   => 'User',
            'reporter_id'     => $agent->user_id,
            'model_type'      => 'ChatSession',
            'model_id'        => $chatSession->id,
        ]);

        StoreChatEvent::make()->handle(
            chatSession: $chatSession,
            eventType: ChatEventTypeEnum::TICKET,
            actorType: ChatActorTypeEnum::AGENT,
            actorId: $agent->id,
            payload: [
                'key'                   => $ticket->reference,
                'url'                   => route('grp.tickets.show', $ticket->reference),
                'summary'               => $ticket->subject,
                'priority_name'         => ChatPriorityEnum::labels()[$ticket->priority->value],
                'created_by_agent_id'   => $agent->id,
                'created_by_agent_name' => $agent->user?->contact_name,
                'created_at'            => now()->toISOString(),
            ]
        );

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'summary'       => ['required', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'priority'      => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'reference_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, Request $request): JsonResponse
    {
        $agent = $this->currentChatAgent();

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can create tickets'], 403);
        }

        $ticket = $this->handle($chatSession, $agent, $request->validate($this->rules()));

        return response()->json([
            'success' => true,
            'message' => 'Ticket created',
            'data'    => [
                'key'           => $ticket->reference,
                'url'           => route('grp.tickets.show', $ticket->reference),
                'summary'       => $ticket->subject,
                'priority_name' => ChatPriorityEnum::labels()[$ticket->priority->value],
            ],
        ]);
    }
}
