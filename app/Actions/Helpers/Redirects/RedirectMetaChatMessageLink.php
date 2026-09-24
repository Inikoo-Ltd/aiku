<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 11:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectMetaChatMessageLink extends OrgAction
{
    public function handle(MetaChatMessage $metaChatMessage): RedirectResponse
    {
        $session = $metaChatMessage->metaChatSession;
        $shop    = $session?->shop;

        if ($session && $shop) {
            return Redirect::to(route('grp.org.chat.inbox', [
                $shop->organisation->slug,
                'channel' => 'whatsapp',
                'session' => $session->ulid,
            ]));
        }

        return Redirect::to(route('grp.chat.reports'));
    }

    public function asController(MetaChatMessage $metaChatMessage, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($metaChatMessage);
    }
}
