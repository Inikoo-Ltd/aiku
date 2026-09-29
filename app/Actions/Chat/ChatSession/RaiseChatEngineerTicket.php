<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One click from the inbox when Jev is sure only a programmer can fix what the customer
 * reports: the customer is added to the open bug ticket it matches, or a CUS ticket is raised
 * with what they wrote, the platform and the symptom already filled in. Only what Jev
 * suggested, once per conversation and never while a ticket is open on it, so staff are not
 * the only thing between the programmers and a flood of tickets.
 */
class RaiseChatEngineerTicket
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * @return array{reference: string, url: string, added: bool}|null
     */
    public function handle(ChatSession|MetaChatSession $chatSession, ChatAgent $agent): ?array
    {
        return Cache::lock('chat-engineer-ticket:'.class_basename($chatSession).':'.$chatSession->id, 60)
            ->get(fn () => $this->raise($chatSession->refresh(), $agent)) ?: null;
    }

    /**
     * @return array{reference: string, url: string, added: bool}|null
     */
    private function raise(ChatSession|MetaChatSession $chatSession, ChatAgent $agent): ?array
    {
        $suggestions = ClassifyChatTurn::suggestions($chatSession);
        $engineer    = $suggestions['engineer'] ?? null;

        if (!$engineer || !empty($engineer['raised']) || $chatSession->tickets()->whereNotIn('status', ['resolved', 'cancelled'])->exists()) {
            return null;
        }

        $known       = !empty($engineer['ticket']['reference']) ? Ticket::where('reference', $engineer['ticket']['reference'])->first() : null;
        $description = $this->description($chatSession, $engineer);

        if ($known) {
            StoreTicketComment::make()->action($known, $agent->user, ['body' => __('Another customer has this problem.')."\n\n".$description]);
            $ticket = $known;
        } else {
            $ticket = StoreTicketFromChatSession::make()->handle($chatSession, $agent, [
                'summary'     => Str::limit(implode(' · ', array_filter([$engineer['platform_label'] ?? null, $engineer['symptom_label'] ?? null, $this->customerName($chatSession)])) ?: __('Problem only a programmer can fix'), 250),
                'description' => $description,
                'kind'        => TicketKindEnum::BUG->value,
                'module'      => $chatSession->shop?->type?->value === 'dropshipping' ? TicketModuleEnum::DROPSHIPPING->value : null,
            ]);
        }

        SetChatSessionMetadata::run($chatSession, [ClassifyChatTurn::RAISED_KEY => $ticket->reference]);
        ClassifyChatTurn::markUsed($chatSession, $suggestions['reading_id'] ?? null, 'engineer', $ticket->reference);

        return ['reference' => $ticket->reference, 'url' => route('grp.tickets.show', $ticket->reference), 'added' => (bool) $known];
    }

    public function asController(ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->respond($metaChatSession);
    }

    private function respond(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        $agent = $this->getAuthorisedChatAgent($chatSession);

        if (!$agent || !$this->userCanDisposeOfChat($agent->user, $chatSession)) {
            return response()->json(['success' => false], 403);
        }

        $result = $this->handle($chatSession, $agent);

        return $result
            ? response()->json(['success' => true, 'data' => $result])
            : response()->json(['success' => false, 'message' => __('There is no ticket to raise for this conversation')], 422);
    }

    /**
     * @param  array<string, mixed>  $engineer
     */
    private function description(ChatSession|MetaChatSession $chatSession, array $engineer): string
    {
        $organisation = $chatSession->shop?->organisation?->slug;
        $chatUrl      = $chatSession instanceof MetaChatSession
            ? route('grp.org.chat.inbox', [$organisation, 'channel' => 'whatsapp', 'session' => $chatSession->ulid])
            : route('grp.org.chat.inbox.conversation', [$organisation, $chatSession->ulid]);

        return implode("\n", array_filter([
            __('Customer').': '.($this->customerName($chatSession) ?: '-').' ('.$chatSession->shop?->name.')',
            __('Conversation').': '.$chatUrl,
            ($engineer['platform_label'] ?? null) ? __('Platform').': '.$engineer['platform_label'] : null,
            ($engineer['symptom_label'] ?? null) ? __('Problem').': '.$engineer['symptom_label'] : null,
            '',
            __('What the customer wrote').':',
            Str::limit(ClassifyChatTurn::customerWrote($chatSession), 3000),
        ], fn ($line) => $line !== null));
    }

    private function customerName(ChatSession|MetaChatSession $chatSession): ?string
    {
        $customer = $chatSession instanceof MetaChatSession ? $chatSession->customer : $chatSession->webUser?->customer;

        return $customer?->name;
    }
}
