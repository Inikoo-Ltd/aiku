<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\HumanResources\Employee;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\ModelHasContent;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;
use stdClass;

class SaveModelImage
{
    use AsAction;

    public function handle(
        User|WebUser|Agent|Supplier|Employee|Guest|Customer|Group|Organisation|Shop|WebBlockType|ProductCategory|Webpage|Collection|ModelHasContent $model,
        array $imageData,
        string $scope = 'image',
        string $foreignkeyMedia = 'image_id'
    ): User|WebUser|Agent|Supplier|Employee|Guest|Customer|Group|Organisation|Shop|WebBlockType|ProductCategory|Webpage|Collection|ModelHasContent {
        $oldImage = $model->image;

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
        if ($model instanceof Group) {
            $group_id = $model->id;
        }
        if ($model instanceof Organisation) {
            $organisation_id = $model->id;
        }


        if ($media) {
            $model->updateQuietly([$foreignkeyMedia => $media->id]);

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
