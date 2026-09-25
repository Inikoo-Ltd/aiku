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
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Collection;

trait WithBlockingTickets
{
    /**
     * What this conversation is waiting on: tickets raised from it that were marked as blocking and are not settled yet, and colleagues' open tasks for it.
     *
     * @return Collection<int, Ticket|StaffTask>
     */
    protected function unresolvedBlockers(ChatSession|MetaChatSession $session): Collection
    {
        $tickets = Ticket::where('source_type', class_basename($session))
            ->where('source_id', $session->id)
            ->where('blocks_source', true)
            ->whereNotIn('status', [TicketStatusEnum::RESOLVED->value, TicketStatusEnum::CANCELLED->value])
            ->orderBy('id')
            ->get();

        return $tickets->toBase()->merge($session->staffTasks()->open()->orderBy('id')->get());
    }

    protected function blockersMessage(Collection $blockers): string
    {
        return __('This chat is waiting on :references. Resolve or cancel it before closing the chat.', [
            'references' => $blockers->pluck('reference')->join(', ', ' '.__('and').' '),
        ]);
    }
}
