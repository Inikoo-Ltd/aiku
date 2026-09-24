<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CallbackShopMailbox extends OrgAction
{
    use WithActionUpdate;

    public function handle(string $code, array $state): RedirectResponse
    {
        $shop = Shop::findOrFail($state['shop_id']);

        $tokens = GmailClient::exchangeCode($code, route('grp.gmail.callback'));

        $refreshToken = Arr::get($tokens, 'refresh_token');

        if (blank($refreshToken)) {
            return Redirect::to($state['return'])->with('notification', [
                'status'      => 'error',
                'title'       => __('Gmail not connected'),
                'description' => __('Google did not return a refresh token. Remove the app from your Google account and try again.'),
            ]);
        }

        $profile = Http::withToken($tokens['access_token'])
            ->throw()
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')
            ->json();

        $mailbox = Arr::get($profile, 'emailAddress');

        // A mailbox belongs to one shop. Connected to two, both fetch the same inbox every minute
        // and every mail is taken in twice, once under each shop: the same conversation appears
        // twice in the inbox and two people can answer it separately. It has to be refused here,
        // because by the time it is saved there is nothing left to tell the copies apart.
        $takenBy = Shop::where('id', '!=', $shop->id)
            ->where('settings->gmail->email', $mailbox)
            ->first();

        if ($takenBy) {
            return Redirect::to($state['return'])->with('notification', [
                'status'      => 'error',
                'title'       => __('Gmail not connected'),
                'description' => __(':mailbox is already the mailbox of :shop. Disconnect it there first, or connect this shop to a mailbox of its own.', [
                    'mailbox' => $mailbox,
                    'shop'    => $takenBy->name,
                ]),
            ]);
        }

        $settings = $shop->settings ?? [];
        data_set($settings, 'gmail', [
            'email'                => $mailbox,
            'refresh_token'        => Crypt::encryptString($refreshToken),
            'history_id'           => Arr::get($profile, 'historyId'),
            'connected_at'         => now()->toIso8601String(),
            'connected_by_user_id' => $state['user_id'],
        ]);

        $this->update($shop, ['settings' => $settings]);

        return Redirect::to($state['return'])->with('notification', [
            'status'      => 'success',
            'title'       => __('Gmail connected'),
            'description' => __('This shop\'s mailbox is now connected.'),
        ]);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return Redirect::route('grp.dashboard.show')->with('notification', [
                'status'      => 'error',
                'title'       => __('Gmail not connected'),
                'description' => (string) $request->query('error'),
            ]);
        }

        $state = json_decode(Crypt::decryptString((string) $request->query('state')), true);

        $shop = Shop::findOrFail($state['shop_id']);

        $this->initialisationFromShop($shop, $request);

        try {
            return $this->handle((string) $request->query('code'), $state);
        } catch (RequestException $exception) {
            return Redirect::to($state['return'])->with('notification', [
                'status'      => 'error',
                'title'       => __('Gmail not connected'),
                'description' => Arr::get($exception->response->json(), 'error.message') ?? Arr::get($exception->response->json(), 'error_description') ?? $exception->getMessage(),
            ]);
        }
    }
}
