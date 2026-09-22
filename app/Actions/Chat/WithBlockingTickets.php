<?php

/*
 * Author: Louis Perez
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat;

use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Database\Eloquent\Collection;

trait WithBlockingTickets
{
    /**
     * Tickets raised from this conversation that were marked as blocking and are not settled yet.
     *
     * @return Collection<int, Ticket>
     */
    protected function unresolvedBlockingTickets(ChatSession|MetaChatSession $session): Collection
    {
        return Ticket::where('source_type', class_basename($session))
            ->where('source_id', $session->id)
            ->where('blocks_source', true)
            ->whereNotIn('status', [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CANCELLED->value])
            ->orderBy('id')
            ->get();
    }

    protected function blockingTicketsMessage(Collection $tickets): string
    {
        return __('This chat is waiting on :references. Resolve or cancel it before closing the chat.', [
            'references' => $tickets->pluck('reference')->join(', ', ' '.__('and').' '),
        ]);
    }
}
