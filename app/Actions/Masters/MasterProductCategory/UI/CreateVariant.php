<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Masters\MasterAsset; 
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;

class CreateVariant extends OrgAction
{
    private MasterShop|MasterProductCategory|Group $parent;

      public function handle(MasterAsset $masterAsset): MasterAsset
    {
        return $masterAsset;
    }

    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): MasterAsset
    {
        $this->parent = $masterShop;
        $group        = group();
        return $this->handle($masterProduct);
    }

    public function htmlResponse(MasterAsset $masterAsset, ActionRequest $request): Response
    {
        return Inertia::render(
            'Masters/CreateVariant',
            [
                'breadcrumbs' => [],
                'title'       => __('Create Variant'),
                'pageHead'    => [
                    'title'   => __('Create Variant'),
                ],
                'master_asset' => $masterAsset
            ]
        );
    }


    public function getBreadcrumbs(MasterShop|Group|MasterProductCategory $parent, MasterProductCategory $masterFamily, string $routeName, array $routeParameters): array
    {
        return ShowMasterFamily::make()->getBreadcrumbs(
            masterFamily: $masterFamily,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
