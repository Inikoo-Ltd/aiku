<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 May 2024 08:37:52 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Web\WebBlock;
use App\Models\Web\Website;
use stdClass;

trait WithAttachMediaToModel
{
    protected function attachMediaToModel(Group|Organisation|Shop|User|Webuser|Website|WebBlock|Product|MasterProductCategory $model, Media $media, string $scope = 'default', string $subScope = null, $data = null): Group|Organisation|Shop|User|Website|Webuser|WebBlock|Product|MasterProductCategory
    {


        $group_id = $model->group_id;

        if ($model instanceof Group || $model instanceof User) {
            $organisation_id = null;
            if ($model instanceof Group) {
                $group_id = $model->id;
            }
        } elseif ($model instanceof Organisation) {
            $organisation_id = $model->id;
        } else {
            $organisation_id = $model->organisation_id;
        }

        if (!$data) {
            $data = new stdClass();
        }

        $model->images()->attach(
            [
                $media->id => [
                    'group_id'        => $group_id,
                    'organisation_id' => $organisation_id,
                    'scope'           => $scope,
                    'sub_scope'       => $subScope,
                    'data'            => json_encode($data)
                ]
            ]
        );

        return $model;
    }

}
