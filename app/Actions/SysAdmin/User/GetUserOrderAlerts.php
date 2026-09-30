<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderAlertTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserOrderAlerts
{
    use AsObject;

    public const array OVERVIEW_ROLES = [
        RolesEnum::GROUP_ADMIN,
        RolesEnum::ORG_ADMIN,
        RolesEnum::MASTERS_MANAGER,
        RolesEnum::MASTERS_MEDIA,
        RolesEnum::MASTERS_CLERK,
        RolesEnum::MASTERS_VIEWER,
    ];

    public const array SHOP_ROLES = [
        RolesEnum::SHOP_ADMIN,
        RolesEnum::SHOPKEEPER_SUPERVISOR,
        RolesEnum::SHOPKEEPER_CLERK,
        RolesEnum::MARKETING_SUPERVISOR,
        RolesEnum::MARKETING_CLERK,
        RolesEnum::SHOP_PPC,
        RolesEnum::PPC_SUPERVISOR,
        RolesEnum::PPC_CLERK,
        RolesEnum::WEBMASTER_SUPERVISOR,
        RolesEnum::WEBMASTER_CLERK,
    ];

    public const array POPUP_DEFAULTS = ['show' => true];

    /**
     * @return array{shops: array<int, array<int, string>>, sounds: array<string, string>, popup: array{show: bool}}
     */
    public function handle(User $user): array
    {
        $saved = Arr::get($user->settings, 'order_alerts', []);

        if (Arr::has($saved, 'types')) {
            $enabledTypes = collect(Arr::get($saved, 'types'))->filter(fn ($type) => Arr::get($type, 'enabled'))->keys()->values()->all();
            $shops        = collect(Arr::get($saved, 'shops', []))->mapWithKeys(fn ($shopId) => [(int) $shopId => $enabledTypes])->all();
        } else {
            $shops = $this->defaultShopTypes($user);
        }

        $hearableShopIds = $this->shopOptions($user)->pluck('id')->all();
        $shops           = collect($shops)
            ->filter(fn (array $types, int $shopId) => $types !== [] && in_array($shopId, $hearableShopIds, true))
            ->all();

        return [
            'shops'  => $shops,
            'sounds' => collect(OrderAlertTypeEnum::cases())->mapWithKeys(fn (OrderAlertTypeEnum $type) => [
                $type->value => Arr::get($saved, "types.$type->value.muted") ? 'silent' : Arr::get($saved, "types.$type->value.sound", $type->defaultSound()),
            ])->all(),
            'popup'  => array_merge(self::POPUP_DEFAULTS, Arr::only(Arr::get($saved, 'popup', []), ['show'])),
        ];
    }

    /**
     * @return array{shops: array<int, int>, types: array<string, array{enabled: bool, sound: string, muted: bool}>, popup: array{show: bool}}
     */
    public function formValue(User $user): array
    {
        $saved = Arr::get($user->settings, 'order_alerts', []);

        if (Arr::has($saved, 'types')) {
            $shopIds      = Arr::get($saved, 'shops', []);
            $enabledTypes = collect(Arr::get($saved, 'types'))->filter(fn ($type) => Arr::get($type, 'enabled'))->keys()->all();
        } else {
            $defaults     = $this->defaultShopTypes($user);
            $shopIds      = array_keys($defaults);
            $enabledTypes = array_unique(array_merge([], ...array_values($defaults)));
        }

        return [
            'shops' => array_values(array_map('intval', $shopIds)),
            'types' => collect(OrderAlertTypeEnum::cases())->mapWithKeys(fn (OrderAlertTypeEnum $type) => [
                $type->value => [
                    'enabled' => in_array($type->value, $enabledTypes, true),
                    'sound'   => Arr::get($saved, "types.$type->value.sound", $type->defaultSound()),
                    'muted'   => (bool) Arr::get($saved, "types.$type->value.muted", false),
                ],
            ])->all(),
            'popup' => array_merge(self::POPUP_DEFAULTS, Arr::only(Arr::get($saved, 'popup', []), ['show'])),
        ];
    }

    public function canHear(User $user, int|Shop $shop): bool
    {
        $shop = $shop instanceof Shop ? $shop : Shop::find($shop);

        return $shop
            && $shop->group_id === $user->group_id
            && $user->authTo(["orders.$shop->id.view", "accounting.$shop->organisation_id.view", 'group-overview', 'masters', 'masters.view']);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function defaultShopTypes(User $user): array
    {
        $shopTypes = [];

        foreach ($this->rolesWithScope($user) as $role) {
            $baseName = $role->scope_type === 'Group' ? $role->name : preg_replace('/-\d+$/', '', $role->name);

            $isShopRole = in_array($baseName, array_map(fn (RolesEnum $role) => $role->value, self::SHOP_ROLES), true);
            if (!$isShopRole && !in_array($baseName, array_map(fn (RolesEnum $role) => $role->value, self::OVERVIEW_ROLES), true)) {
                continue;
            }

            $types = $isShopRole
                ? array_values(array_diff(OrderAlertTypeEnum::values(), [OrderAlertTypeEnum::ECOM_SMALL->value]))
                : [OrderAlertTypeEnum::ECOM_BIG->value];

            foreach ($this->shopIdsInScope($user, $role->scope_type, $role->scope_id) as $shopId) {
                $shopTypes[$shopId] = array_values(array_unique(array_merge($shopTypes[$shopId] ?? [], $types)));
            }
        }

        return $shopTypes;
    }

    /**
     * @return Collection<int, Shop>
     */
    public function shopOptions(User $user): Collection
    {
        return $this->alertableShops($user)
            ->orderBy('name')
            ->get(['id', 'group_id', 'organisation_id', 'code', 'name', 'type'])
            ->filter(fn (Shop $shop) => $this->canHear($user, $shop))
            ->values();
    }

    private function rolesWithScope(User $user): Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'User')
            ->where('model_has_roles.model_id', $user->id)
            ->get(['roles.name', 'roles.scope_type', 'roles.scope_id']);
    }

    /**
     * @return array<int, int>
     */
    private function shopIdsInScope(User $user, string $scopeType, int $scopeId): array
    {
        if (!in_array($scopeType, ['Group', 'Organisation', 'Shop'], true)) {
            return [];
        }

        return $this->alertableShops($user)
            ->when($scopeType === 'Organisation', fn ($query) => $query->where('organisation_id', $scopeId))
            ->when($scopeType === 'Shop', fn ($query) => $query->where('id', $scopeId))
            ->pluck('id')
            ->all();
    }

    private function alertableShops(User $user)
    {
        return Shop::where('group_id', $user->group_id)
            ->whereIn('type', [ShopTypeEnum::B2B, ShopTypeEnum::B2C, ShopTypeEnum::DROPSHIPPING])
            ->where('state', ShopStateEnum::OPEN);
    }
}
