<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Http\Resources\Helpers\TicketResource;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Helpers\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatSessionTickets
{
    use AsAction;

    public function handle(ChatSession|MetaChatSession $session): AnonymousResourceCollection
    {
        $customer = $this->customer($session);

        $tickets = Ticket::with(['reporter', 'customer', 'assignee', 'collaborators', 'organisation', 'source'])
            ->where(function ($query) use ($session, $customer) {
                $query->where(function ($sourced) use ($session) {
                    $sourced->where('source_type', class_basename($session))
                        ->where('source_id', $session->id);
                });

                if ($customer) {
                    $query->orWhere('customer_id', $customer->id);
                }
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return TicketResource::collection($tickets);
    }

    private function customer(ChatSession|MetaChatSession $session): ?Customer
    {
        return $session instanceof MetaChatSession
            ? $session->customer
            : $session->webUser?->customer;
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->handle($chatSession)->resolve()]);
    }

    public function inMetaChatSession(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->handle($metaChatSession)->resolve()]);
    }
}
