<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-38m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\AddItemNotOnPurchaseOrderToStockDelivery;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileStockDeliveryAddableItems
{
    use AsController;
    use WithMobileStockDeliveries;

    private const int LIMIT = 30;

    /**
     * What the web offers as a product not on the purchase orders: the supplier's products, or for a partner
     * delivery our SKOs the partner stocks. A scanned SKO narrows it to the products that land on that SKO.
     *
     * @return array<int, array{id: int, field: string, code: string|null, name: string|null, sko: array{id: int, code: string, name: string|null, packed_in: float|null}|null}>
     */
    public function handle(StockDelivery $stockDelivery, ?string $search, ?int $orgStockId): array
    {
        $search = trim((string) $search);

        if (AddItemNotOnPurchaseOrderToStockDelivery::isPartnerDelivery($stockDelivery)) {
            return AddItemNotOnPurchaseOrderToStockDelivery::addablePartnerOrgStocks($stockDelivery)
                ->when($orgStockId, fn (Builder $query) => $query->where('org_stocks.id', $orgStockId))
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereStartWith('org_stocks.code', $search)->orWhereAnyWordStartWith('org_stocks.name', $search)))
                ->orderBy('org_stocks.code')
                ->limit(self::LIMIT)
                ->get(['org_stocks.id', 'org_stocks.code', 'org_stocks.name', 'org_stocks.packed_in'])
                ->map(fn (OrgStock $orgStock) => [
                    'id'    => $orgStock->id,
                    'field' => 'org_stock_id',
                    'code'  => $orgStock->code,
                    'name'  => $orgStock->name,
                    'sko'   => $this->sko($orgStock->id, $orgStock->code, $orgStock->name, $orgStock->packed_in),
                ])
                ->values()
                ->all();
        }

        $matches = self::linkedOrgStocksSql();

        return AddItemNotOnPurchaseOrderToStockDelivery::addableOrgSupplierProducts($stockDelivery)
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->leftJoinLateral(
                fn ($linked) => $linked->from('org_stocks')
                    ->where('org_stocks.organisation_id', $stockDelivery->organisation_id)
                    ->whereRaw($matches)
                    ->orderByRaw("case when org_stocks.state in ('".OrgStockStateEnum::DISCONTINUING->value."', '".OrgStockStateEnum::DISCONTINUED->value."') then 1 else 0 end")
                    ->orderByRaw('exists (select 1 from org_stock_has_org_supplier_products where org_stock_has_org_supplier_products.org_stock_id = org_stocks.id and org_stock_has_org_supplier_products.org_supplier_product_id = org_supplier_products.id) desc')
                    ->orderBy('org_stocks.id')
                    ->limit(1)
                    ->select(['org_stocks.id as sko_id', 'org_stocks.code as sko_code', 'org_stocks.name as sko_name', 'org_stocks.packed_in as sko_packed_in']),
                'sko'
            )
            ->when($orgStockId, fn (Builder $query) => $query->whereExists(fn ($scanned) => $scanned->selectRaw('1')
                ->from('org_stocks')
                ->where('org_stocks.id', $orgStockId)
                ->whereRaw($matches)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereStartWith('supplier_products.code', $search)
                ->orWhereAnyWordStartWith('supplier_products.name', $search)
                ->orWhereStartWith('sko.sko_code', $search)))
            ->orderBy('supplier_products.code')
            ->limit(self::LIMIT)
            ->get(['org_supplier_products.id', 'supplier_products.code', 'supplier_products.name', 'sko.sko_id', 'sko.sko_code', 'sko.sko_name', 'sko.sko_packed_in'])
            ->map(fn ($row) => [
                'id'    => $row->id,
                'field' => 'org_supplier_product_id',
                'code'  => $row->code,
                'name'  => $row->name,
                'sko'   => $row->sko_id ? $this->sko((int) $row->sko_id, $row->sko_code, $row->sko_name, $row->sko_packed_in) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The SKOs ResolveOrgStockForSupplierProduct can land on, read only: linked to the org supplier product, or
     * holding a stock of the supplier product, directly or through a shared trade unit.
     */
    private static function linkedOrgStocksSql(): string
    {
        return "(exists (select 1 from org_stock_has_org_supplier_products
                where org_stock_has_org_supplier_products.org_stock_id = org_stocks.id
                and org_stock_has_org_supplier_products.org_supplier_product_id = org_supplier_products.id)
            or org_stocks.stock_id in (select stock_has_supplier_products.stock_id from stock_has_supplier_products
                where stock_has_supplier_products.supplier_product_id = supplier_products.id)
            or org_stocks.stock_id in (select stock_units.model_id from model_has_trade_units as stock_units
                join model_has_trade_units as product_units on product_units.trade_unit_id = stock_units.trade_unit_id
                    and product_units.model_type = 'SupplierProduct' and product_units.model_id = supplier_products.id
                where stock_units.model_type = 'Stock'))";
    }

    /**
     * @return array{id: int, code: string, name: string|null, packed_in: float|null}
     */
    private function sko(int $id, string $code, ?string $name, mixed $packedIn): array
    {
        return ['id' => $id, 'code' => $code, 'name' => $name, 'packed_in' => $packedIn === null ? null : (float) $packedIn];
    }

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);

        if (!AddItemNotOnPurchaseOrderToStockDelivery::acceptsItems($stockDelivery)) {
            $this->refuseState(__('Items can only be added while the delivery is being booked in'));
        }

        $filters = Validator::make($request->query(), [
            'q'            => ['sometimes', 'nullable', 'string', 'max:64'],
            'org_stock_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        return response()->json([
            'data' => $this->handle($stockDelivery, $filters['q'] ?? null, isset($filters['org_stock_id']) ? (int) $filters['org_stock_id'] : null),
        ]);
    }
}
