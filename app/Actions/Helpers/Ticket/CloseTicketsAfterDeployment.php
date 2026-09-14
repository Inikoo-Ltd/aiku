<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 19:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseTicketsAfterDeployment
{
    use AsAction;

    public function handle(): int
    {
        $closed = 0;

        Ticket::where('status', TicketStatusEnum::PENDING_DEPLOY)->cursor()->each(function (Ticket $ticket) use (&$closed) {
            $deployComment = data_get($ticket->data, 'deploy_comment');
            $ticket->update(['data' => Arr::except($ticket->data ?? [], 'deploy_comment')]);

            $author = User::find(data_get($deployComment, 'user_id'));
            if ($author && data_get($deployComment, 'body')) {
                StoreTicketComment::make()->action($ticket, $author, ['body' => $deployComment['body']], notifyUsers: false);
            }

            UpdateTicket::make()->action($ticket->refresh(), ['status' => TicketStatusEnum::RESOLVED->value]);
            $closed++;
        });

        return $closed;
    }
}
