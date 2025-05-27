<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Models\Helpers\Brand;
use App\Models\Web\Website;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

class SaveModelLogo
{
    use AsAction;

    public function handle(
        Website|Brand $model,
        array $imageData,
        string $scope = 'image'
    ): Website|Brand {
        $oldImage = $model->logo;

        $checksum = md5_file($imageData['path']);

        if ($oldImage && $oldImage->checksum == $checksum) {
            return $model;
        }

        data_set($imageData, 'checksum', $checksum);
        $media = StoreMediaFromFile::run($model, $imageData, 'image');

        if ($oldImage && $oldImage->id == $media->id) {
            return $model;
        }

        $group_id        = $model->group_id;
        $organisation_id = $model->organisation_id;

        if ($media) {
            $model->updateQuietly(['logo_id' => $media->id]);

            $model->images()->sync(
                [
                    $media->id => [
                        'group_id'        => $group_id,
                        'organisation_id' => $organisation_id,
                        'scope'           => $scope,
                        'data'            => json_encode(new stdClass())
                    ]
                ]
            );
            if ($oldImage) {
                $oldImage->delete();
            }
        }

        return $model;
    }

}
