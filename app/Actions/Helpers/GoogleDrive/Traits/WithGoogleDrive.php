<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 11 Jul 2023 10:03:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\GoogleDrive\Traits;

use App\Actions\Traits\WithActionUpdate;
use Exception;
use Google_Client;
use Google_Service_Drive;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

trait WithGoogleDrive
{
    use WithTokenPath;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function authorize(Organisation $organisation): string
    {
        $client = new Google_Client();
        $google = Arr::get($organisation->settings, 'google');

        $tokenPath = $this->getTokenPath($organisation);
        $authCode  = request()->query('code');
        $client->setRedirectUri(route('grp.org.settings.google_drive.callback', $organisation->slug));
        $client->setApplicationName('Aiku google drive manager');
        $client->setAuthConfig([
            'client_id'     => Arr::get($google, 'id'),
            'client_secret' => Arr::get($google, 'secret')
        ]);

        $client->setAccessType('offline');
        $client->setScopes(
            [
                Google_Service_Drive::DRIVE_METADATA,
                Google_Service_Drive::DRIVE_FILE,
                Google_Service_Drive::DRIVE
            ]
        );

        if (blank($authCode)) {
            // If there is no previous token, or it's expired.
            if ($client->isAccessTokenExpired()) {
                // Refresh the token if possible, else fetch a new one.
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                } else {
                    // Request authorization from the user.
                    return $client->createAuthUrl();
                }
            }
        }

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }

        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));

        return route('grp.sysadmin.settings.edit');
    }

    /**
     * @throws \Exception
     */
    public function callback(Organisation $organisation): RedirectResponse
    {
        $client = new Google_Client();

        $tokenPath       = $this->getTokenPath($organisation);
        $authCode        = request()->query('code');

        $client->setRedirectUri(route('grp.org.settings.google_drive.callback', $organisation->slug));
        $client->setApplicationName('Aiku google drive manager');
        $client->setAuthConfig([
            'client_id'     => Arr::get($organisation->settings, 'google.id'),
            'client_secret' => Arr::get($organisation->settings, 'google.secret')
        ]);

        $client->setAccessType('offline');
        $client->setScopes(
            [
                Google_Service_Drive::DRIVE_METADATA,
                Google_Service_Drive::DRIVE_FILE,
                Google_Service_Drive::DRIVE
            ]
        );

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        $client->setAccessToken($accessToken);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }

        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));

        $this->update($organisation, [
            'settings' => [
                'google' => [
                    ...$organisation->settings['google'] ?? [],
                    'token' => $client->getAccessToken()
                ]
            ]
        ]);

        return redirect()->route('grp.sysadmin.settings.edit');
    }
}
