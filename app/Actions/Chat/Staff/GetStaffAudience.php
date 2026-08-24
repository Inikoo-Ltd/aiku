<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffAudience
{
    use AsAction;

    public const AUDIENCES = ['crm', 'warehouse'];

    public const ACTIVE_WITHIN_MINUTES = 15;

    public static function label(string $audience): string
    {
        return match ($audience) {
            'crm'       => __('CRM'),
            'warehouse' => __('Warehouse'),
        };
    }

    public static function isActive(int $userId): bool
    {
        $lastActive = Cache::get("staff-last-active:{$userId}");

        return $lastActive && $lastActive >= now()->subMinutes(self::ACTIVE_WITHIN_MINUTES)->timestamp;
    }

    /**
     * @return Collection<int, User>
     */
    public function handle(string $audience, DeliveryNote|Order|PickingSession $context): Collection
    {
        foreach ($this->shopScopes($context) as $shop) {
            $users = $this->pickFromLists(
                Arr::get($shop->settings ?? [], "staff_chat.{$audience}_user_ids", []),
                Arr::get($shop->settings ?? [], "staff_chat.{$audience}_backup_user_ids", []),
            );
            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        $organisation = Organisation::find($context->organisation_id);
        $users        = $this->pickFromLists(
            Arr::get($organisation?->settings ?? [], "staff_chat.{$audience}_user_ids", []),
            Arr::get($organisation?->settings ?? [], "staff_chat.{$audience}_backup_user_ids", []),
        );
        if ($users->isNotEmpty()) {
            return $users;
        }

        if ($context instanceof PickingSession) {
            return $this->handlePickingSession($audience, $context);
        }

        [$scope, $roles] = match ($audience) {
            'crm'       => [Shop::find($context->shop_id), [RolesEnum::CUSTOMER_SERVICE_CLERK, RolesEnum::CUSTOMER_SERVICE_SUPERVISOR]],
            'warehouse' => [
                $context instanceof DeliveryNote ? Warehouse::find($context->warehouse_id) : Warehouse::where('organisation_id', $context->organisation_id)->first(),
                [RolesEnum::DISPATCH_CLERK, RolesEnum::DISPATCH_SUPERVISOR, RolesEnum::STOCK_CONTROLLER],
            ],
        };

        if (!$scope) {
            return new Collection();
        }

        $roleNames = array_map(fn (RolesEnum $role) => RolesEnum::getRoleName($role->value, $scope), $roles);

        return User::where('status', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames))
            ->get();
    }

    /**
     * @return array<int, Shop>
     */
    private function shopScopes(DeliveryNote|Order|PickingSession $context): array
    {
        if ($context instanceof PickingSession) {
            return Shop::whereIn('id', $context->deliveryNotes()->pluck('shop_id')->unique())->get()->all();
        }

        $shop = Shop::find($context->shop_id);

        return $shop ? [$shop] : [];
    }

    private function pickFromLists(array $primaryIds, array $backupIds): Collection
    {
        if (!empty($primaryIds)) {
            $activePrimary = User::whereIn('id', $primaryIds)->where('status', true)->get()->filter(fn (User $user) => self::isActive($user->id));
            if ($activePrimary->isNotEmpty()) {
                return $activePrimary->values();
            }
        }

        if (!empty($backupIds)) {
            $activeBackup = User::whereIn('id', $backupIds)->where('status', true)->get()->filter(fn (User $user) => self::isActive($user->id));
            if ($activeBackup->isNotEmpty()) {
                return $activeBackup->values();
            }
        }

        $allIds = array_unique(array_merge($primaryIds, $backupIds));
        if (empty($allIds)) {
            return new Collection();
        }

        return User::whereIn('id', $allIds)->where('status', true)->get();
    }

    private function handlePickingSession(string $audience, PickingSession $context): Collection
    {
        if ($audience === 'warehouse') {
            $warehouse = Warehouse::find($context->warehouse_id);
            if (!$warehouse) {
                return new Collection();
            }
            $roleNames = array_map(
                fn (RolesEnum $role) => RolesEnum::getRoleName($role->value, $warehouse),
                [RolesEnum::DISPATCH_CLERK, RolesEnum::DISPATCH_SUPERVISOR, RolesEnum::STOCK_CONTROLLER]
            );

            return User::where('status', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames))
                ->get();
        }

        $shops = Shop::whereIn('id', $context->deliveryNotes()->pluck('shop_id')->unique())->get();
        if ($shops->isEmpty()) {
            return new Collection();
        }

        $roleNames = [];
        foreach ($shops as $shop) {
            foreach ([RolesEnum::CUSTOMER_SERVICE_CLERK, RolesEnum::CUSTOMER_SERVICE_SUPERVISOR] as $role) {
                $roleNames[] = RolesEnum::getRoleName($role->value, $shop);
            }
        }

        return User::where('status', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames))
            ->get();
    }
}
