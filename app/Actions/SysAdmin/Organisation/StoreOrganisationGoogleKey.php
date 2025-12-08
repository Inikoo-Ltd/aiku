<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:15:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation;

use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrganisationGoogleKey
{
    use AsAction;
    use WithAttributes;

    public function handle(Organisation $organisation, array $modelData): Organisation
    {
        $organisation->update(
            [
                'settings' => [
                    'google' => [
                        'id' => $modelData['google_cloud_client_id'],
                        'secret' => $modelData['google_cloud_client_secret'],
                        'drive' => [
                            'folder' => $modelData['google_drive_folder_key'],
                        ],

                    ],
                ],
            ]
        );

        return $organisation;
    }

    public string $commandSignature = 'org:set-google-key {organisation} {google_cloud_client_id} {google_cloud_client_secret} {google_drive_folder_key} ';

    public function asCommand(Command $command): int
    {
        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception) {
            $command->error('Organisation not found');

            return 1;
        }

        $this->handle(
            $organisation,
            [
                'google_cloud_client_id' => $command->argument('google_cloud_client_id'),
                'google_cloud_client_secret' => $command->argument('google_cloud_client_secret'),
                'google_drive_folder_key' => $command->argument('google_drive_folder_key'),
            ]
        );

        return 0;
    }
}
