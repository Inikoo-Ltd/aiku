<?php

/*
 * Author: Eka Yudinata <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\SysAdmin\Task\GetUserNotificationTask;
use App\Enums\Task\SubTaskTypeEnum;
use App\Enums\Task\TaskStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\SubTask;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterUpdatedBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($this->pendingSubTasks($user) as $subTask) {
            /** @var Product $product */
            $product = $subTask->model;

            if (!$product) {
                continue;
            }

            $shop = $product->shop;
            $org  = $shop->organisation;

            if (!isset($organisationsMap[$org->slug])) {
                $organisationsMap[$org->slug] = [
                    'organisation' => [
                        'slug' => $org->slug,
                        'name' => $org->name,
                        'code' => $org->code,
                    ],
                    'shops' => [],
                ];
            }

            if (!isset($organisationsMap[$org->slug]['shops'][$shop->slug])) {
                $organisationsMap[$org->slug]['shops'][$shop->slug] = [
                    'slug'     => $shop->slug,
                    'name'     => $shop->name,
                    'code'     => $shop->code,
                    'products' => [],
                    'route'    => [
                        'name'       => 'grp.org.shops.show.catalogue.products.all_products.index',
                        'parameters' => [
                            'organisation'   => $org->slug,
                            'shop'           => $shop->slug,
                            'index_elements' => ['state' => 'price_not_match_master'],
                        ],
                    ],
                ];
            }

            $organisationsMap[$org->slug]['shops'][$shop->slug]['products'][] = [
                'sub_task_id' => $subTask->id,
                'code'        => $product->code,
                'name'        => $product->name,
                'slug'        => $product->slug,
            ];
        }

        return array_values(array_map(
            fn (array $organisation) => [
                ...$organisation,
                'shops' => array_values(array_map(
                    fn (array $shop) => [...$shop, 'count' => count($shop['products'])],
                    $organisation['shops']
                )),
            ],
            $organisationsMap
        ));
    }

    /**
     * Read from the hydrated column rather than counting rows, since this runs on every page load.
     * Scoped to the notification task because the user's other tasks carry their own sub task
     * counts, which have nothing to do with this badge.
     */
    public function totalCount(User $user): int
    {
        return (int)$user->tasks()
            ->where('tasks.code', GetUserNotificationTask::TASK_TYPE_CODE)
            ->value('tasks.number_subtasks');
    }

    /**
     * @return Collection<int, SubTask>
     */
    private function pendingSubTasks(User $user): Collection
    {
        return $this->pendingSubTaskQuery($user)
            ->with('model.shop.organisation')
            ->orderBy('id')
            ->get();
    }

    private function pendingSubTaskQuery(User $user): Builder
    {
        return SubTask::whereIn('task_id', $user->tasks()->select('tasks.id'))
            ->where('type', SubTaskTypeEnum::MASTER_PRICE_DRIFT->value)
            ->where('status', TaskStatusEnum::PENDING->value);
    }

    /**
     * Products that opted out of master pricing and whose price or RRP no longer
     * matches the master value for the product's own currency code. A drift in
     * either one is enough; a master with no entry for that currency is skipped.
     *
     * Shared by the sub task producer and by the products index element filter, so the two
     * can never report a different set.
     */
    public function applyDriftConstraints(Builder|QueryBuilder $query): void
    {
        $query
            ->where('products.not_follow_master_prices', true)
            ->where('products.is_for_sale', true)
            ->whereNotNull('products.master_product_id')
            ->join('master_assets', 'master_assets.id', '=', 'products.master_product_id')
            ->join('currencies', 'currencies.id', '=', 'products.currency_id')
            ->where(function ($query) {
                $query
                    ->whereRaw("jsonb_exists(master_assets.master_prices, currencies.code)
                        AND products.price <> (master_assets.master_prices #>> ARRAY[currencies.code, 'value'])::numeric")
                    ->orWhereRaw("jsonb_exists(master_assets.master_rrps, currencies.code)
                        AND products.rrp <> (master_assets.master_rrps #>> ARRAY[currencies.code, 'value'])::numeric");
            });
    }
}
