<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Actions\Helpers\Ticket\ApplyTicketSearch;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchTickets
{
    use AsAction;

    public function handle(string $query): array
    {
        $user    = request()->user();
        $tickets = Ticket::where('tickets.group_id', $user->group_id)->visibleTo($user)->select('tickets.*');

        if (ApplyTicketSearch::run($tickets, $query, $user)) {
            $tickets->orderByDesc('search_rank');
        }

        $hash = ApplyTicketSearch::commitHash($query);

        return [
            'scope'   => 'tickets',
            'results' => [
                'tickets' => $tickets->orderByDesc('tickets.updated_at')->limit(15)->get()->map(fn (Ticket $ticket) => [
                    'id'         => $ticket->id,
                    'code'       => $ticket->reference,
                    'name'       => $ticket->subject,
                    'state_icon' => TicketStatusEnum::stateIcon()[$ticket->status->value],
                    'commits'    => $hash ? $this->matchedCommits($ticket, $hash) : [],
                    'href'       => route('grp.tickets.show', $ticket->reference),
                ])->all(),
            ],
        ];
    }

    /**
     * The deployed commits a searched hash points at, so a result says which commit put it there.
     *
     * @return array<int, array{hash: string, subject: string, version: string|null, deployed_at: string|null}>
     */
    private function matchedCommits(Ticket $ticket, string $hash): array
    {
        return collect(data_get($ticket->data, 'commits', []))
            ->filter(fn (array $commit) => str_starts_with(strtolower($commit['hash'] ?? ''), $hash))
            ->map(fn (array $commit) => Arr::only($commit, ['hash', 'subject', 'version', 'deployed_at']))
            ->values()
            ->all();
    }
}
