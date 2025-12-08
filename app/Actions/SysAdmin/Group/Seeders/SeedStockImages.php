<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedStockImages
{
    use AsAction;
    use WithAttachMediaToModel;

    public function handle(Group $group): void
    {
        foreach (glob(resource_path('art/stock_images/*/*/*/*')) as $filename) {
            $_filename = Str::after($filename, resource_path('art/stock_images'));

            if (preg_match('/\/(.*)\/(.*)\/(.*)\/(.*)/', $_filename, $fileData)) {

                $checksum = md5_file($filename);
                if ($group->images()->where('collection_name', 'stock-image')->where('checksum', $checksum)->exists()) {
                    continue;
                }

                $scope = $fileData[1];
                $subScope = $fileData[2].'-'.$fileData[3];
                $imageName = $fileData[3];

                $data = [
                    'stock_image' => [
                        'category' => $fileData[1],
                        'sub_category' => $fileData[2],
                        'tag' => $fileData[3],
                    ],
                ];
                $imageData = [
                    'path' => $filename,
                    'originalName' => $imageName,
                    'checksum' => $checksum,
                ];

                $media = StoreMediaFromFile::run($group, $imageData, 'stock-image');

                $this->attachMediaToModel($group, $media, $scope, $subScope, $data);

            }
        }
    }

    public string $commandSignature = 'group:seed-stock-images';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding stock images for group: $group->name");
            $this->handle($group);
        }

        return 0;
    }
}
