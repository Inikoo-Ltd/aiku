<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;
use App\Models\Masters\MasterCollection;

enum MasterCollectionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';

    case PRODUCTS = 'products';
    case FAMILIES = 'families';
    case COLLECTIONS = 'collections';

    case HISTORY = 'history';
    case SHOP_COLLECTIONS = 'shop_collections';


    public static function navigationWithStats(MasterCollection $masterCollection): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) use ($masterCollection) {
            $blueprint = $case->blueprint();

            if ($case == self::PRODUCTS) {
                $blueprint['number'] = $masterCollection->stats->number_current_master_families;
            } elseif ($case == self::FAMILIES) {
                $blueprint['number'] = $masterCollection->stats->number_current_master_products;
            } elseif ($case == self::COLLECTIONS) {
                $blueprint['number'] = $masterCollection->stats->number_current_master_collections;
            }

            return [$case->value => $blueprint];
        })->all();
    }

    public function blueprint(): array
    {
        return match ($this) {
            MasterCollectionTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',

            ],
            MasterCollectionTabsEnum::FAMILIES => [
                'title' => __('Master Families'),
                'icon'  => 'fal fa-folder',
            ],
            MasterCollectionTabsEnum::PRODUCTS => [
                'title' => __('Master Products'),
                'icon'  => 'fal fa-cube',
            ],
            MasterCollectionTabsEnum::COLLECTIONS => [
                'title' => __('Master Collections'),
                'icon'  => 'fal fa-album-collection',
            ],
            MasterCollectionTabsEnum::SHOP_COLLECTIONS => [
                'align' => 'right',
                'title' => __('Shop Collections'),
                'icon'  => 'fal fa-store',
            ],
            MasterCollectionTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
