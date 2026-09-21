<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\MetaChatSession\StoreMetaChatEvent;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketSourceChannelEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreTicketFromChatSession
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession|MetaChatSession $session, ChatAgent $agent, array $modelData): Ticket
    {
        $description = trim((string) Arr::get($modelData, 'description', ''));
        if ($referenceUrl = Arr::get($modelData, 'reference_url')) {
            $description = trim($description."\n\nReference: ".$referenceUrl);
        }

        $isWhatsapp = $session instanceof MetaChatSession;
        $webUser    = $isWhatsapp ? null : $session->webUser;
        $customer   = $isWhatsapp ? $session->customer : $webUser?->customer;

        $ticket = StoreTicket::make()->action($agent->user->group, [
            'type'            => TicketTypeEnum::CUSTOMER->value,
            'subject'         => Arr::get($modelData, 'summary'),
            'description'     => $description ?: null,
            'priority'        => Arr::get($modelData, 'priority', ChatPriorityEnum::NORMAL->value),
            'kind'            => Arr::get($modelData, 'kind'),
            'organisation_id' => $session->shop?->organisation_id,
            'shop_id'         => $session->shop_id,
            'customer_id'     => $customer?->id,
            'reporter_type'   => 'User',
            'reporter_id'     => $agent->user_id,
            'source_type'     => class_basename($session),
            'source_id'       => $session->id,
            'source_channel'  => $this->channel($session)->value,
            'blocks_source'   => (bool) Arr::get($modelData, 'blocks_source', false),
            'images'          => Arr::get($modelData, 'images', []),
        ]);

        $payload = [
            'key'                   => $ticket->reference,
            'blocks_source'         => $ticket->blocks_source,
            'url'                   => route('grp.tickets.show', $ticket->reference),
            'summary'               => $ticket->subject,
            'priority_name'         => ChatPriorityEnum::labels()[$ticket->priority->value],
            'created_by_agent_id'   => $agent->id,
            'created_by_agent_name' => $agent->user?->contact_name,
            'created_at'            => now()->toISOString(),
        ];

        if ($isWhatsapp) {
            StoreMetaChatEvent::make()->handle(
                metaChatSession: $session,
                eventType: ChatEventTypeEnum::TICKET,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: $payload
            );
        } else {
            StoreChatEvent::make()->handle(
                chatSession: $session,
                eventType: ChatEventTypeEnum::TICKET,
                actorType: ChatActorTypeEnum::AGENT,
                actorId: $agent->id,
                payload: $payload
            );
        }

        return $ticket;
    }

    private function channel(ChatSession|MetaChatSession $session): TicketSourceChannelEnum
    {
        if ($session instanceof MetaChatSession) {
            return TicketSourceChannelEnum::WHATSAPP;
        }

        return TicketSourceChannelEnum::tryFrom($session->channel?->value ?? '')
            ?? TicketSourceChannelEnum::WEBSITE;
    }

    public function rules(): array
    {
        return [
            'summary'       => ['required', 'string', 'max:255'],
            'description'   => ['sometimes', 'nullable', 'string'],
            'priority'      => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'kind'          => ['sometimes', 'nullable', Rule::in(TicketKindEnum::chatValues())],
            'reference_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'blocks_source' => ['sometimes', 'boolean'],
            'images'        => ['sometimes', 'array', 'max:5'],
            'images.*'      => Ticket::ticketFileRules(),
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, Request $request): JsonResponse
    {
        return $this->respond($chatSession, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMetaChatSession(?string $organisation, MetaChatSession $metaChatSession, Request $request): JsonResponse
    {
        return $this->respond($metaChatSession, $request);
    }

    private function respond(ChatSession|MetaChatSession $session, Request $request): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($session);

        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Only authenticated agents can create tickets'], 403);
        }

        if (!$this->userCanDisposeOfChat($agent->user, $session)) {
            return response()->json(['success' => false, 'message' => $this->chatHeldByAnotherAgentMessage($session)], 403);
        }

        $ticket = $this->handle($session, $agent, $request->validate($this->rules()));

        return response()->json([
            'success' => true,
            'message' => 'Ticket created',
            'data'    => [
                'key'           => $ticket->reference,
                'blocks_source' => $ticket->blocks_source,
                'url'           => route('grp.tickets.show', $ticket->reference),
                'summary'       => $ticket->subject,
                'priority_name' => ChatPriorityEnum::labels()[$ticket->priority->value],
            ],
        ]);
    }
}
