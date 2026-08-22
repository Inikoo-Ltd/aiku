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
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffAudience
{
    use AsAction;

    public const AUDIENCES = ['crm', 'warehouse'];

    public static function label(string $audience): string
    {
        return match ($audience) {
            'crm'       => __('CRM'),
            'warehouse' => __('Warehouse'),
        };
    }

    /**
     * ponytail: audience = holders of the scoped spatie roles; refine per job position when management asks
     *
     * @return Collection<int, User>
     */
    public function handle(string $audience, DeliveryNote|Order|PickingSession $context): Collection
    {
        $configuredUserIds = Arr::get(
            \App\Models\SysAdmin\Organisation::find($context->organisation_id)?->settings ?? [],
            "staff_chat.{$audience}_user_ids",
            []
        );
        if (!empty($configuredUserIds)) {
            return User::whereIn('id', $configuredUserIds)->where('status', true)->get();
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
