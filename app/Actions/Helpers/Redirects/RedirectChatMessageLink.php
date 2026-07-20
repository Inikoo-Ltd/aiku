<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectChatMessageLink extends GrpAction
{
    public function handle(ChatMessage $chatMessage): RedirectResponse
    {
        $session = $chatMessage->chatSession;
        $shop    = $session?->shop;

        if ($session && $shop) {
            return Redirect::to(route('grp.org.chat.conversations.detail', [
                $shop->organisation->slug,
                $session->id,
            ]));
        }

        return Redirect::to(route('grp.chat.dashboard'));
    }

    public function asController(ChatMessage $chatMessage, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($chatMessage);
    }
}
