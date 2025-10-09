<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:33:20 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\GoogleDrive;

use App\Models\SysAdmin\Organisation;
use Exception;
use Google_Service_Drive_DriveFile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class UploadFileGoogleDrive
{
    use AsAction;


    public string $commandSignature = 'drive:upload {organisation} {filename}';

    /**
     * @throws \Google\Exception
     */
    public function handle(Organisation $organisation, $path, $folderName): string
    {
        $client = GetClientGoogleDrive::run($organisation);
        $name   = Str::of($path)->basename();

        $folders = explode('/', $folderName);
        $currentId = null;

        foreach ($folders as $folder) {
            $existingFolder = $this->getFolderIfExists($client, $folder, $currentId);
            $currentId = $existingFolder ?: $this->createFolder($client, $folder, $currentId);
        }

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'parents' => [$currentId]
        ]);

        $file = $client->files->create(
            $fileMetadata,
            [
                'data' => file_get_contents($path),
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]
        );

        return $file->id;
    }

    public function createFolder($client, $folderName, $parentId = null): string
    {
        $folderMetadata = new Google_Service_Drive_DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ]);

        if ($parentId) {
            $folderMetadata->setParents([$parentId]);
        }

        $folder = $client->files->create($folderMetadata, [
            'fields' => 'id'
        ]);

        return $folder->id;
    }

    public function getFolderIfExists($client, $folderName, $parentId = null): ?string
    {
        $query = "mimeType='application/vnd.google-apps.folder' and name='" . $folderName . "' and trashed=false";
        if ($parentId) {
            $query .= " and '" . $parentId . "' in parents";
        }

        $results = $client->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive'
        ]);

        return !empty($results->getFiles()) ? $results->getFiles()[0]->getId() : null;
    }

    /**
     * @throws \Google\Exception
     */
    public function asCommand(Command $command): string
    {
        try {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        } catch (Exception) {
            $command->error('Organisation not found');

            return 1;
        }

        return $this->handle($organisation, $command->argument('filename'));
    }
}
