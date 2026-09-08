<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession\UI;

use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectToOrgChatInbox
{
    use AsAction;

    public function asController(ActionRequest $request): RedirectResponse
    {
        $agent = $request->user()?->chatAgent;
        abort_unless((bool) $agent, 403, __('Only chat agents can access the inbox'));

        $organisation = $agent->organisations()->orderBy('organisations.id')->first();
        abort_unless((bool) $organisation, 404, __('You are not assigned to any chat scope'));

        return redirect()->route('grp.org.chat.inbox', [$organisation->slug]);
    }
}
