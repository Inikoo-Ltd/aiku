<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Aug 2026 13:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFaireSkippedBadgeData
{
    use AsObject;

    public function handle(User $user): array
    {
        $organisationsMap = [];

        foreach ($this->faireShops($user) as $shop) {
            $skipped = Arr::get($shop->settings, 'faire.skipped_orders', []);
            $org     = $shop->organisation;

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

            $organisationsMap[$org->slug]['shops'][] = [
                'slug'           => $shop->slug,
                'name'           => $shop->name,
                'code'           => $shop->code,
                'skipped_orders' => [
                    'count'  => count($skipped),
                    'orders' => array_values($skipped),
                ],
            ];
        }

        return array_values($organisationsMap);
    }

    public function totalCount(User $user): int
    {
        $total = 0;

        foreach ($this->faireShops($user) as $shop) {
            $total += count(Arr::get($shop->settings, 'faire.skipped_orders', []));
        }

        return $total;
    }

    private function faireShops(User $user): Collection
    {
        return $user->authorisedShops()->with('organisation')->get()
            ->filter(
                fn (Shop $shop) => $shop->engine == ShopEngineEnum::FAIRE
                    && $user->authTo("orders.{$shop->id}.edit")
            );
    }
}
