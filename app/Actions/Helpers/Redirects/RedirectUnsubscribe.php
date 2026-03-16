<?php

/*
 * Author: Eka yudinata <ekayudinata@gmail.com>
 * Created: Wed, 15 Jan 2026 16:22:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectUnsubscribe
{
    use AsAction;

    public function handle(DispatchedEmail $dispatchedEmail, string $encryptedDispatchedEmailID): RedirectResponse
    {
        $baseUrl = null;

        $recipient = $dispatchedEmail->recipient;
        if ($recipient instanceof Customer || $recipient instanceof Prospect) {
            $shop = $recipient->shop;
        } else {
            abort(404, 'Recipient not found');
        }


        if ($shop->website) {
            $baseUrl = $shop->website->getUrl();
        }

        if ($baseUrl) {
            return Redirect::away($baseUrl.'/unsubscribe/'.$encryptedDispatchedEmailID);
        }

        abort(404, 'Shop website not found');
    }

    public function asController(string $encryptedDispatchedEmailID, ActionRequest $request): RedirectResponse
    {
        $dispatchedEmailID = Crypt::decryptString($encryptedDispatchedEmailID);
        $dispatchedEmail   = DispatchedEmail::findOrFail($dispatchedEmailID);

        return $this->handle($dispatchedEmail, $encryptedDispatchedEmailID);
    }
}
