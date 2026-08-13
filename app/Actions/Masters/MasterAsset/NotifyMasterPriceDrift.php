<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\SysAdmin\Task\GetUserNotificationTask;
use App\Actions\SysAdmin\Task\StoreSubTask;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Enums\Task\SubTaskTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class NotifyMasterPriceDrift
{
    use AsAction;
    public string $jobQueue = 'long-running';

    /**
     * Run after MasterAssetHydrateMasterPricesRRPtoChild, so the products that follow the
     * master have already been repriced and only the genuine opt-outs are still drifted.
     */
    public function handle(MasterAsset $masterAsset): void
    {
        $query = Product::where('products.master_product_id', $masterAsset->id)
            ->select('products.*');

        GetMasterUpdatedBadgeData::make()->applyDriftConstraints($query);

        $products = $query->with('shop.organisation')->get();

        foreach ($products->groupBy('shop_id') as $shopProducts) {
            /** @var Shop $shop */
            $shop  = $shopProducts->first()->shop;
            $users = $this->usersAllowedToViewProducts($shop);

            foreach ($users as $user) {
                $task = GetUserNotificationTask::run($user);

                foreach ($shopProducts as $product) {
                    StoreSubTask::run($task, $this->driftSubTaskData($product, $shop));
                }
            }
        }
    }

    /**
     * Authorisation over the shop is not the permission on its products, so every candidate
     * is still checked one by one. authTo() binds the permissions team per user, which the
     * User::permission() scope does not: in a queue worker that scope reads whichever group
     * the previous job left bound.
     */
    private function usersAllowedToViewProducts(Shop $shop): Collection
    {
        $permission = ShopPermissionsEnum::getPermissionName('products.view', $shop);

        return $shop->authorisedUsers()
            ->get()
            ->filter(fn (User $user) => $user->authTo($permission))
            ->values();
    }

    private function driftSubTaskData(Product $product, Shop $shop): array
    {
        return [
            'type'        => SubTaskTypeEnum::MASTER_PRICE_DRIFT->value,
            'model_type'  => 'Product',
            'model_id'    => $product->id,
            'name'        => __('Product price no longer matches master'),
            'description' => __(':product in :shop kept its own price after the master was updated.', [
                'product' => $product->code,
                'shop'    => $shop->name,
            ]),
            'data'        => [
                'slug'  => $product->slug,
                'route' => [
                    'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                    'parameters' => [
                        'organisation'   => $shop->organisation->slug,
                        'shop'           => $shop->slug,
                        'index_elements' => ['state' => 'price_not_match_master'],
                    ],
                ],
            ],
        ];
    }
}
