<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Helpers\Ticket\StoreTicket;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One click from the Verba gate for a staff member Slack could not be matched to: raises a help desk ticket to link them,
 * or returns the one already open, so they are helped instead of skipping Slack.
 */
class RequestSlackLink
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return (bool) SlackWriteAccess::joinPending($request->user());
    }

    public function handle(User $user): Ticket
    {
        $subject = __('Link :name to their Slack account', ['name' => $user->contact_name ?: $user->username]);

        return Ticket::where('reporter_type', 'User')->where('reporter_id', $user->id)->where('subject', $subject)
            ->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED])->first()
            ?? StoreTicket::make()->action($user->group, [
                'subject'       => $subject,
                'description'   => __('Verba could not find :name (:email) in the company\'s Slack, so they cannot connect yet. Link their aiku user to their Slack person (slack:link-users), then ask them to open Verba again.', ['name' => $user->contact_name ?: $user->username, 'email' => $user->email]),
                'kind'          => TicketKindEnum::SUPPORT->value,
                'module'        => TicketModuleEnum::CHAT->value,
                'reporter_type' => 'User',
                'reporter_id'   => $user->id,
            ]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        RecordVerbaJoinEvent::run($request->user()->id, 'asked_link');

        return response()->json(['reference' => $this->handle($request->user())->reference]);
    }
}
