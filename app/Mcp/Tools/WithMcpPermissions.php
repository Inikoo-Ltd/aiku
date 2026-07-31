<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\McpRequest;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait WithMcpPermissions
{
    /**
     * Spatie permissions are team scoped: without the team id every check returns
     * false and authTo() then caches that false for a week. MCP requests do not
     * carry the group binding the web middleware provides, so set it explicitly.
     */
    protected function userCan(Request $request, string $permission): bool
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user) {
            return false;
        }

        setPermissionsTeamId($user->group_id);

        return $user->authTo($permission);
    }

    /**
     * @return Collection<int, array{slug: string, code: string, name: string}>
     */
    protected function accessibleShops(Request $request): Collection
    {
        return Shop::orderBy('id')->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Shop $shop) => $this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::ORDERS_VIEW->value, $shop))
                || $this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_VIEW->value, $shop)))
            ->map(fn (Shop $shop) => [
                'slug' => $shop->slug,
                'code' => $shop->code,
                'name' => $shop->name,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{slug: string, code: string, name: string}>
     */
    protected function accessibleOrganisations(Request $request): Collection
    {
        return Organisation::orderBy('id')->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Organisation $organisation) => $this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::ACCOUNTING_VIEW->value, $organisation)))
            ->map(fn (Organisation $organisation) => [
                'slug' => $organisation->slug,
                'code' => $organisation->code,
                'name' => $organisation->name,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{slug: string, code: string, name: string}>
     */
    protected function accessibleWarehouses(Request $request): Collection
    {
        return Warehouse::orderBy('id')->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Warehouse $warehouse) => $this->userCan($request, WarehousePermissionsEnum::getPermissionName(WarehousePermissionsEnum::STOCKS_VIEW->value, $warehouse)))
            ->map(fn (Warehouse $warehouse) => [
                'slug' => $warehouse->slug,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    protected function recentlyQueried(Request $request, string $argument): Collection
    {
        $user = $request->user();
        if (!$user) {
            return collect();
        }

        return McpRequest::where('user_id', $user->id)
            ->where('is_error', false)
            ->whereNotNull("arguments->{$argument}")
            ->latest('id')
            ->limit(20)
            ->pluck("arguments->{$argument}")
            ->unique()
            ->take(3)
            ->values();
    }

    /**
     * Listing the real options in the error beats telling the assistant to call
     * another tool: the logs show it guesses again instead of following that hint.
     *
     * @param Collection<int, array{slug: string, code: string, name: string}> $options
     */
    protected function notFoundError(string $type, string $given, Collection $options, Request $request): Response
    {
        if ($options->isEmpty()) {
            return Response::error("You do not have access to any {$type}.");
        }

        $list = $options
            ->map(fn (array $option) => "{$option['code']} ({$option['name']})")
            ->implode(', ');

        $message = "'{$given}' does not match any {$type} you can query. Use one of these codes exactly: {$list}.";

        $recent = $this->recentlyQueried($request, $type);
        if ($recent->isNotEmpty()) {
            $message .= " This user most recently queried: {$recent->implode(', ')}.";
        }

        return Response::error($message);
    }
}
