<?php

/*
 * Author: Eka yudinata <ekayudinata@gmail.com>
 * Created: Thu, 15 Jan 2026 16:22:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Helpers\Redirects;

use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectUnsubscribe
{
    use AsAction;

    public function handle(DispatchedEmail $dispatchedEmail, ?string $tag = null): RedirectResponse
    {
        $baseUrl = null;

        if ($tag === 'prospect') {
            $prospectDispatchedEmail = DB::table('prospect_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($prospectDispatchedEmail) {
                $prospect = Prospect::find($prospectDispatchedEmail->prospect_id);
                $shop = Shop::find($prospect->shop_id);
            } else {
                abort(404, 'Prospect recipient not found');
            }
        } else {
            $customerDispatchedEmail = DB::table('customer_has_dispatched_emails')->where('dispatched_email_id', $dispatchedEmail->id)->first();

            if ($customerDispatchedEmail) {
                $customer = Customer::find($customerDispatchedEmail->customer_id);
                $shop = $customer->shop;
            } else {
                abort(404, 'Customer recipient not found');
            }
        }


        if ($shop->website) {
            $baseUrl = $shop->website->getUrl();
        }

        if ($baseUrl) {
            $safeId = urlencode(Crypt::encryptString($dispatchedEmail->id));

            $redirectUrl = $baseUrl . '/unsubscribe/' . $safeId;
            if ($tag) {
                $redirectUrl .= '?tag=' . $tag;
            }

            return Redirect::away($redirectUrl);
        }

        abort(404, 'Shop website not found');
    }

    public function asController(string $encryptedDispatchedEmailID, ActionRequest $request): RedirectResponse
    {
        try {
            $dispatchedEmailID = Crypt::decryptString($encryptedDispatchedEmailID);
            $dispatchedEmail   = DispatchedEmail::findOrFail($dispatchedEmailID);

            $tag = $request->get('tag');

            return $this->handle($dispatchedEmail, $tag);
        } catch (DecryptException $e) {
            return Redirect::route('grp.unsubscribe-error');
        }
    }
}
