<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the shops, organisations and warehouses this user can ask about, with the exact short name (slug) each other tool expects. Call this first when a question names a shop, organisation or warehouse in words, instead of guessing the slug.')]
#[IsReadOnly]
class MyAccessTool extends Tool
{
    use WithMcpPermissions;

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if (!$user) {
            return Response::error('Not authenticated.');
        }

        $shops = Shop::orderBy('id')->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Shop $shop) => $this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::ORDERS_VIEW->value, $shop))
                || $this->userCan($request, ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::PRODUCTS_VIEW->value, $shop)))
            ->map(fn (Shop $shop) => [
                'slug' => $shop->slug,
                'code' => $shop->code,
                'name' => $shop->name,
            ])
            ->values();

        $organisations = Organisation::orderBy('id')->get(['id', 'slug', 'name'])
            ->filter(fn (Organisation $organisation) => $this->userCan($request, OrganisationPermissionsEnum::getPermissionName(OrganisationPermissionsEnum::ACCOUNTING_VIEW->value, $organisation)))
            ->map(fn (Organisation $organisation) => [
                'slug' => $organisation->slug,
                'name' => $organisation->name,
            ])
            ->values();

        $warehouses = Warehouse::orderBy('id')->get(['id', 'slug', 'code', 'name'])
            ->filter(fn (Warehouse $warehouse) => $this->userCan($request, WarehousePermissionsEnum::getPermissionName(WarehousePermissionsEnum::STOCKS_VIEW->value, $warehouse)))
            ->map(fn (Warehouse $warehouse) => [
                'slug' => $warehouse->slug,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ])
            ->values();

        return Response::json([
            'user'          => $user->contact_name ?? $user->username,
            'shops'         => $shops,
            'organisations' => $organisations,
            'warehouses'    => $warehouses,
            'hint'          => 'Use the slug values above as the shop, organisation or warehouse argument of the other tools. An empty list means this user has no access at that level.',
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
