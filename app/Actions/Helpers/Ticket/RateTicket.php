<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RateTicket
{
    use AsAction;

    public static function canRate(Ticket $ticket, User|WebUser|null $user): bool
    {
        return $user !== null
            && !$ticket->status->isOpen()
            && $ticket->rating === null
            && $ticket->reporter_id === $user->id
            && $ticket->reporter_type === ($user instanceof User ? 'User' : 'WebUser');
    }

    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        $ticket->update([
            'rating'         => Arr::get($modelData, 'rating'),
            'rating_comment' => Arr::get($modelData, 'comment'),
            'rated_at'       => now(),
        ]);

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canRate($request->route('ticket'), $request->user());
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        return $this->handle($ticket, $request->validated());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
