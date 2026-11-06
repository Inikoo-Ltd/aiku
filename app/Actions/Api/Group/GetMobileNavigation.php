<?php

/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use App\Actions\UI\Grp\Layout\GetOrganisationNavigation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMobileNavigation
{
    use AsAction;

    private const BOTTOM_BAR_KEYS = ['tasks', 'tickets', 'chat'];

    private const ORGANISATION_INDEX_KEYS = ['shops_index', 'fulfilments_index', 'warehouses_index'];

    public function handle(User $user): array
    {
        $tabs = array_fill_keys(self::BOTTOM_BAR_KEYS, false);

        $groupNavigation = GetGroupNavigation::run($user);
        $tabs            = $this->withTabs($tabs, $groupNavigation);

        $group = null;
        if ($user->hasGroupAccess() || $user->canViewSales()) {
            $group = [
                'slug' => $user->group->slug,
                'name' => $user->group->name,
                'menu' => $this->menu($groupNavigation),
            ];
        }

        $organisations = [];
        foreach ($user->authorisedShopOrganisations as $organisation) {
            $organisationNavigation = GetOrganisationNavigation::run($user, $organisation);
            $tabs                   = $this->withTabs($tabs, $organisationNavigation);

            $shopsNavigation = Arr::get($organisationNavigation, 'shops_fulfilments_navigation.shops_navigation.navigation', []);
            $shops           = [];
            foreach ($user->authorisedShops->where('organisation_id', $organisation->id) as $shop) {
                if ($shop->state === ShopStateEnum::CLOSED) {
                    continue;
                }
                $shopNavigation = Arr::get($shopsNavigation, $shop->slug.'.subNavigation', []);
                $tabs           = $this->withTabs($tabs, $shopNavigation);
                $shops[]        = [
                    'slug' => $shop->slug,
                    'name' => $shop->name,
                    'menu' => $this->menu($shopNavigation),
                ];
            }

            $warehouses = [];
            foreach ($user->authorisedWarehouses()->where('organisation_id', $organisation->id)->get() as $warehouse) {
                $warehouseNavigation = Arr::get($organisationNavigation, 'warehouses_navigation.'.$warehouse->slug, []);
                $tabs                = $this->withTabs($tabs, $warehouseNavigation);
                $warehouses[]        = [
                    'slug' => $warehouse->slug,
                    'name' => $warehouse->name,
                    'menu' => $this->menu($warehouseNavigation),
                ];
            }

            $organisations[] = [
                'slug'       => $organisation->slug,
                'name'       => $organisation->name,
                'menu'       => $this->menu(Arr::except($organisationNavigation, self::ORGANISATION_INDEX_KEYS)),
                'shops'      => $shops,
                'warehouses' => $warehouses,
            ];
        }

        return [
            'tabs'          => $tabs,
            'group'         => $group,
            'organisations' => $organisations,
        ];
    }

    private function withTabs(array $tabs, array $navigation): array
    {
        foreach (self::BOTTOM_BAR_KEYS as $key) {
            if (isset($navigation[$key]['label'])) {
                $tabs[$key] = true;
            }
        }

        return $tabs;
    }

    private function menu(array $navigation): array
    {
        $menu = [];
        foreach ($navigation as $key => $item) {
            if (!is_array($item) || !isset($item['label']) || in_array($key, self::BOTTOM_BAR_KEYS, true)) {
                continue;
            }

            $children = [];
            foreach (Arr::get($item, 'topMenu.subSections', []) as $subSection) {
                if (is_array($subSection) && isset($subSection['label'])) {
                    $children[] = [
                        'label' => $subSection['label'],
                        'icon'  => $this->iconName($subSection['icon'] ?? null),
                    ];
                }
            }

            $menu[] = [
                'key'      => (string) $key,
                'label'    => $item['label'],
                'icon'     => $this->iconName($item['icon'] ?? null),
                'children' => $children,
            ];
        }

        return $menu;
    }

    private function iconName(array|string|null $icon): ?string
    {
        $parts = is_array($icon) ? $icon : explode(' ', (string) $icon);

        foreach ($parts as $part) {
            if (is_string($part) && str_starts_with($part, 'fa-')) {
                return $part;
            }
        }

        return null;
    }

    public function asController(ActionRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($user);
    }
}
