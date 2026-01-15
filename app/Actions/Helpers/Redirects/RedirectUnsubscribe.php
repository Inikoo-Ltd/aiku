<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Jan 2026 16:22:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Comms\DispatchedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectUnsubscribe extends OrgAction
{
    public function handle(DispatchedEmail $dispatchedEmail): RedirectResponse
    {
        // TODO: Fix this redirect URL

        $baseUrl = null;
        if ($dispatchedEmail->shop && $dispatchedEmail->shop->website) {
            $baseUrl = $dispatchedEmail->shop->website->getUrl();
        }

        if ($baseUrl) {
            return Redirect::away($baseUrl . '/unsubscribe/' . $dispatchedEmail->id);
        }

        return Redirect::route('iris.unsubscribe.show', $dispatchedEmail->id);
    }

    public function asController(DispatchedEmail $dispatchedEmail, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($dispatchedEmail->shop, $request);

        return $this->handle($dispatchedEmail);
    }
}
