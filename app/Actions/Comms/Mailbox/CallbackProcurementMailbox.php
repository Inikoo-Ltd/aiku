<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class CallbackProcurementMailbox extends OrgAction
{
    use WithActionUpdate;

    public function handle(Organisation $organisation, string $code, array $state): RedirectResponse
    {
        $tokens = GmailClient::exchangeCode($code, route('grp.gmail.callback'));

        $refreshToken = Arr::get($tokens, 'refresh_token');

        if (blank($refreshToken)) {
            return $this->refuse($state, __('Google did not return a refresh token. Remove the app from your Google account and try again.'));
        }

        $profile = Http::withToken($tokens['access_token'])
            ->throw()
            ->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')
            ->json();

        $mailbox = Arr::get($profile, 'emailAddress');

        $takenBy = Shop::where('settings->gmail->email', $mailbox)->value('name')
            ?? self::procurementMailboxOwner($mailbox, $organisation->id)?->name;

        if ($takenBy) {
            return $this->refuse($state, __(':mailbox is already connected to :owner. Disconnect it there first, or connect a mailbox of its own.', [
                'mailbox' => $mailbox,
                'owner'   => $takenBy,
            ]));
        }

        $settings = $organisation->settings ?? [];
        data_set($settings, 'procurement.gmail', [
            'email'                => $mailbox,
            'refresh_token'        => Crypt::encryptString($refreshToken),
            'history_id'           => Arr::get($profile, 'historyId'),
            'connected_at'         => now()->toIso8601String(),
            'connected_by_user_id' => $state['user_id'],
        ]);

        $this->update($organisation, ['settings' => $settings]);

        return Redirect::to($state['return'])->with('notification', [
            'status'      => 'success',
            'title'       => __('Gmail connected'),
            'description' => __('The procurement mailbox is now connected.'),
        ]);
    }

    public static function procurementMailboxOwner(string $mailbox, ?int $exceptOrganisationId = null): ?Organisation
    {
        return Organisation::where('settings->procurement->gmail->email', $mailbox)
            ->when($exceptOrganisationId, fn ($query) => $query->where('id', '!=', $exceptOrganisationId))
            ->first();
    }

    private function refuse(array $state, string $description): RedirectResponse
    {
        return Redirect::to($state['return'])->with('notification', [
            'status'      => 'error',
            'title'       => __('Gmail not connected'),
            'description' => $description,
        ]);
    }
}
