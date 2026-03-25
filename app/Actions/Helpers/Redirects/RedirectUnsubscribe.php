<?php

/*
 * Author: Eka yudinata <ekayudinata@gmail.com>
 * Created: Thu, 15 Jan 2026 16:22:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectUnsubscribe
{
    use AsAction;

    public function handle(DispatchedEmail $dispatchedEmail): RedirectResponse
    {
        $baseUrl = null;

        $customerDispatchedEmail =  DB::table('customer_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

        //  TODO: update later for propect
        if ($customerDispatchedEmail) {
            $customer = Customer::find($customerDispatchedEmail->customer_id);
            $shop = $customer->shop;
        } else {
            abort(404, 'Recipient not found');
        }


        if ($shop->website) {
            $baseUrl = $shop->website->getUrl();
        }

        if ($baseUrl) {
            $safeId = urlencode(Crypt::encryptString($dispatchedEmail->id));

            return Redirect::away($baseUrl.'/unsubscribe/'.$safeId);
        }

        abort(404, 'Shop website not found');
    }

    public function asController(string $encryptedDispatchedEmailID, ActionRequest $request): RedirectResponse
    {
        $dispatchedEmailID = Crypt::decryptString($encryptedDispatchedEmailID);
        $dispatchedEmail   = DispatchedEmail::findOrFail($dispatchedEmailID);

        return $this->handle($dispatchedEmail);
    }
}
