<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Oct 2024 10:50:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\HumanResources\JobPosition;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithReorganisePositions
{
    public function reorganisePositionsSlugsToIds($positionsWithSlugs): array
    {
        $positions = [];

        if (count($positionsWithSlugs) == 0) {
            return [];
        }

        foreach ($positionsWithSlugs as $positionData) {
            $jobPosition = JobPosition::firstWhere('slug', $positionData['slug']);
            $positions[$jobPosition->id] = $this->reorganiseScopes($positionData['scopes']);
        }

        return $positions;
    }

    private function reorganiseScopes($scopesWithSlugs): array
    {
        $scopes = [];
        foreach ($scopesWithSlugs as $key => $value) {
            $scopeIds = [];
            $scopeModel = $key;
            foreach (Arr::get($value, 'slug', []) as $slug) {
                if ($key == 'organisations') {
                    $scopeModel = 'Organisation';
                    $model = Organisation::where('slug', $slug)->first();
                    if ($model) {
                        $scopeIds[] = $model->id;
                    }
                } elseif ($key == 'shops') {
                    $scopeModel = 'Shop';
                    $model = Shop::where('slug', $slug)->first();
                    if ($model) {
                        $scopeIds[] = $model->id;
                    }

                } elseif ($key == 'productions') {
                    $scopeModel = 'Production';
                    $model = Production::where('slug', $slug)->first();
                    if ($model) {
                        $scopeIds[] = $model->id;
                    }
                } elseif ($key == 'warehouses') {
                    $scopeModel = 'Warehouse';
                    $model = Warehouse::where('slug', $slug)->first();
                    if ($model) {
                        $scopeIds[] = $model->id;
                    }
                } elseif ($key == 'fulfilments') {
                    $scopeModel = 'Fulfilment';
                    $model = Fulfilment::where('slug', $slug)->first();
                    if ($model) {
                        $scopeIds[] = $model->id;
                    }
                }
            }

            $scopes[$scopeModel] = $scopeIds;
        }

        return $scopes;
    }
}
