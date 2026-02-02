<?php

/*
 * Author: Eka yudinata <ekayudinata@gmail.com>
 * Created: Wed, 15 Jan 2026 16:22:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\Comms\DispatchedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectUnsubscribe
{
    use AsAction;

    public function handle(DispatchedEmail $dispatchedEmail): RedirectResponse
    {
        $baseUrl = null;
        if ($dispatchedEmail->shop && $dispatchedEmail->shop->website) {
            $baseUrl = $dispatchedEmail->shop->website->getUrl();
        }

        if ($baseUrl) {
            return Redirect::away($baseUrl . '/unsubscribe/' . $dispatchedEmail->uuid);
        }

        abort(404, 'Shop website not found');
    }

    public function asController(DispatchedEmail $dispatchedEmail, ActionRequest $request): RedirectResponse
    {
        return $this->handle($dispatchedEmail);
    }
}
