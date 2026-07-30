<?php

/*
 * Author Louis Perez
 * Created on 29-07-2026-11h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\Masters\MasterProductCategory\StoreFamilyFromMasterFamily;
use App\Actions\OrgAction;
use App\Events\BroadcastCloneFamilyAndProductsFromMaster;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\ActionRequest;

class CloneFamilyAndProductsFromMaster extends OrgAction implements ShouldBeUnique
{
    public function getJobUniqueId(MasterProductCategory $masterProductCategory)
    {
        return $masterProductCategory->id;
    }

    public function handle(MasterProductCategory $masterFamily, array $modelData, User $user)
    {
        $shops          = Shop::whereIn('id', data_get($modelData, 'shop_ids', []))->get();
        $masterProducts = $masterFamily->masterAssets()->where('master_assets.status', true)->get() ?? [];

        $doneFamilies           = 0;
        $doneProducts           = 0;
        $pendingCount           = count($shops);
        $pendingCountProducts   = count($masterProducts);

        foreach ($shops as $shop) {
            // Could bulk at once, but just gonna do this since this is a dispatch anyway, to handle event broadcast easier (For the soketi progress information)
            StoreFamilyFromMasterFamily::run(
                $masterFamily,
                [
                'shop_family'   => [
                    $shop->id => [
                        'create_webpage'    => true
                    ]
                ]
            ]
            );
            $doneFamilies++;
            BroadcastCloneFamilyAndProductsFromMaster::dispatch($user, $masterFamily, $doneFamilies, $pendingCount, $pendingCountProducts, $doneProducts);
        }

        foreach ($masterProducts as $masterProduct) {
            StoreProductFromMasterProduct::run($masterProduct, [
                'shop_products'     => $shops->mapWithKeys(function ($item) {
                    return [
                        $item->id   => [
                            'create_in_shop'    => true
                        ]
                    ];
                }),
                'is_minion_variant' => $masterProduct->is_minion_variant,
                'is_for_sale'       => $masterProduct->is_for_sale
            ]);
            $doneProducts++;
            BroadcastCloneFamilyAndProductsFromMaster::dispatch($user, $masterFamily, $doneFamilies, $pendingCount, $pendingCountProducts, $doneProducts);
        }
    }

    public function rules(): array
    {
        return [
            'shop_ids'  => ['required', 'array']
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisationFromGroup($masterProductCategory, group(), $request);

        SELF::dispatch($this->masterFamily, $this->validatedData, $request->user());
    }
}
