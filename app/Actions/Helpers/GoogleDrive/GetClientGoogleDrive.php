<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:33:20 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\GoogleDrive;

use App\Actions\Helpers\GoogleDrive\Traits\WithTokenPath;
use App\Models\SysAdmin\Organisation;
use Google_Client;
use Google_Service_Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetClientGoogleDrive
{
    use AsAction;
    use WithTokenPath;

    /**
     * @throws \Google\Exception
     */
    public function handle(Organisation $organisation): Google_Service_Drive
    {
        $tokenPath = $this->getTokenPath($organisation);

        $client = $this->getClient($organisation, $tokenPath);

        return new Google_Service_Drive($client);
    }

    /**
     * @throws \Google\Exception
     */
    public function getClient(Organisation $organisation, $tokenPath): RedirectResponse|Google_Client
    {
        $client       = new Google_Client();

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

        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        return $client;
    }
}
