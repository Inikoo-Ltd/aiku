<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use Closure;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingShopIds;
use App\Actions\Procurement\OrgPartner\PartnerSkoPrice;
use App\Actions\Procurement\OrgSupplierProducts\ResolveOrgStockForSupplierProduct;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The supplier sent a product that is on none of the delivery's purchase orders and we keep it: it becomes a line
 * of the delivery, expected and counted as what arrived, priced at the supplier product's cost. Purchase orders are
 * left as they are: the delivery is what costs the stock. A partner delivery takes one of our SKOs the partner also
 * stocks, priced as a partner purchase order line is.
 */
class AddItemNotOnPurchaseOrderToStockDelivery extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDelivery $stockDelivery;

    /**
     * @return Builder<OrgSupplierProduct>
     */
    public static function addableOrgSupplierProducts(StockDelivery $stockDelivery): Builder
    {
        $parentColumn = match ($stockDelivery->parent_type) {
            'OrgSupplier' => 'org_supplier_id',
            'OrgAgent'    => 'org_agent_id',
            default       => null,
        };

        return OrgSupplierProduct::query()
            ->where('org_supplier_products.organisation_id', $stockDelivery->organisation_id)
            ->where('org_supplier_products.state', OrgSupplierProductStateEnum::ACTIVE->value)
            ->when($parentColumn, fn (Builder $query) => $query->where('org_supplier_products.'.$parentColumn, $stockDelivery->parent_id), fn (Builder $query) => $query->whereRaw('false'));
    }

    /**
     * @return Builder<OrgStock>
     */
    public static function addablePartnerOrgStocks(StockDelivery $stockDelivery): Builder
    {
        $orgPartner = $stockDelivery->parent_type === 'OrgPartner' ? OrgPartner::find($stockDelivery->parent_id) : null;

        return OrgStock::query()
            ->where('org_stocks.organisation_id', $stockDelivery->organisation_id)
            ->whereNotIn('org_stocks.state', [OrgStockStateEnum::DISCONTINUING->value, OrgStockStateEnum::DISCONTINUED->value])
            ->when(
                $orgPartner,
                fn (Builder $query) => $query
                    ->whereExists(fn ($sellerStocks) => $sellerStocks->selectRaw('1')->from('org_stocks as seller_org_stocks')
                        ->where('seller_org_stocks.organisation_id', $orgPartner->partner_id)
                        ->whereColumn('seller_org_stocks.stock_id', 'org_stocks.stock_id')
                        ->whereRaw('coalesce('.PartnerSkoPrice::pricePerSkoSql('seller_org_stocks.id', GetPartnerSellingShopIds::run($orgPartner->partner), (string) $orgPartner->id).', 0) > 0'))
                    ->whereNotIn('org_stocks.id', StockDeliveryItem::where('stock_delivery_id', $stockDelivery->id)->whereNotNull('org_stock_id')->select('org_stock_id')),
                fn (Builder $query) => $query->whereRaw('false')
            );
    }

    public static function isPartnerDelivery(StockDelivery $stockDelivery): bool
    {
        return $stockDelivery->parent_type === 'OrgPartner';
    }

    public static function acceptsItems(StockDelivery $stockDelivery): bool
    {
        return in_array($stockDelivery->parent_type, ['OrgSupplier', 'OrgAgent', 'OrgPartner'], true)
            && !$stockDelivery->is_costed
            && ($stockDelivery->isInGoodsIn() || $stockDelivery->state === StockDeliveryStateEnum::BOOKED_IN);
    }

    public function rules(): array
    {
        if (self::isPartnerDelivery($this->stockDelivery)) {
            return [
                'org_stock_id' => [
                    'required',
                    'integer',
                    function (string $attribute, mixed $value, Closure $fail) {
                        if ($this->stockDelivery->items()->where('org_stock_id', $value)->exists()) {
                            $fail(__('This SKO is already on this delivery, change its checked quantity instead'));
                        } elseif (!self::addablePartnerOrgStocks($this->stockDelivery)->whereKey($value)->exists()) {
                            $fail(__('This SKO is not stocked by :parent', ['parent' => $this->stockDelivery->parent_name ?? __('this partner')]));
                        }
                    },
                ],
                'sko_quantity' => ['required', 'numeric', 'gt:0'],
            ];
        }

        return [
            'org_supplier_product_id' => ['required', 'integer', Rule::in(self::addableOrgSupplierProducts($this->stockDelivery)->pluck('id')->all())],
            'sko_quantity'            => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'org_supplier_product_id.in' => __('This product is not supplied by :parent', ['parent' => $this->stockDelivery->parent_name ?? __('this supplier')]),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->is_costed) {
            $validator->errors()->add($this->itemField(), __('The delivery is costed: reopen the costing before adding items'));
        } elseif (!self::acceptsItems($this->stockDelivery)) {
            $validator->errors()->add($this->itemField(), __('Items can only be added while the delivery is being booked in'));
        }
    }

    private function itemField(): string
    {
        return self::isPartnerDelivery($this->stockDelivery) ? 'org_stock_id' : 'org_supplier_product_id';
    }

    public function handle(StockDelivery $stockDelivery, User $user, array $modelData): StockDeliveryItem
    {
        if (self::isPartnerDelivery($stockDelivery)) {
            return $this->addPartnerOrgStock($stockDelivery, $user, OrgStock::findOrFail($modelData['org_stock_id']), (float) $modelData['sko_quantity']);
        }

        $orgSupplierProduct = OrgSupplierProduct::findOrFail($modelData['org_supplier_product_id']);
        $supplierProduct    = $orgSupplierProduct->supplierProduct;
        $orgStock           = ResolveOrgStockForSupplierProduct::run($stockDelivery->organisation, $supplierProduct);

        if (!$orgStock) {
            throw ValidationException::withMessages(['org_supplier_product_id' => __(':code is not linked to any of our SKOs', ['code' => $supplierProduct->code])]);
        }
        if ($stockDelivery->items()->where('org_stock_id', $orgStock->id)->exists()) {
            throw ValidationException::withMessages(['org_supplier_product_id' => __('This SKO is already on this delivery, change its checked quantity instead')]);
        }

        $units    = round((float) $modelData['sko_quantity'] * (float) ($orgStock->packed_in ?: 1), 4);
        $exchange = $supplierProduct->currency_id === $stockDelivery->currency_id ? 1.0 : (float) GetCurrencyExchange::run($supplierProduct->currency, $stockDelivery->currency);

        return DB::transaction(function () use ($stockDelivery, $user, $orgSupplierProduct, $supplierProduct, $orgStock, $units, $exchange) {
            $stockDeliveryItem = StoreStockDeliveryItem::make()->action($stockDelivery, $supplierProduct->historicSupplierProduct, $orgStock, [
                'unit_quantity' => $units,
                'net_amount'    => round((float) $supplierProduct->cost * $units * $exchange, 2),
                'state'         => StockDeliveryItemStateEnum::RECEIVED,
            ], strict: false);

            $stockDeliveryItem->update([
                'org_supplier_product_id' => $orgSupplierProduct->id,
                'received_at'             => now(),
                'data'                    => ['not_on_purchase_order' => ['by' => $user->id, 'at' => now()->toIso8601String()]],
            ]);

            return SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => $units]);
        });
    }

    private function addPartnerOrgStock(StockDelivery $stockDelivery, User $user, OrgStock $orgStock, float $skoQuantity): StockDeliveryItem
    {
        $unitCost = StorePurchaseOrderTransaction::make()->partnerUnitCost(OrgPartner::findOrFail($stockDelivery->parent_id), $orgStock);
        if (!$unitCost || $unitCost <= 0) {
            throw ValidationException::withMessages(['org_stock_id' => __(':partner does not sell :code', ['partner' => $stockDelivery->parent_name, 'code' => $orgStock->code])]);
        }

        $units = round($skoQuantity * (float) ($orgStock->packed_in ?: 1), 4);

        return DB::transaction(function () use ($stockDelivery, $user, $orgStock, $units, $unitCost) {
            $stockDeliveryItem = StoreStockDeliveryItem::make()->action($stockDelivery, null, $orgStock, [
                'unit_quantity' => $units,
                'net_amount'    => round($unitCost * $units, 2),
                'state'         => StockDeliveryItemStateEnum::RECEIVED,
            ], strict: false);

            $stockDeliveryItem->update([
                'received_at' => now(),
                'data'        => ['not_on_purchase_order' => ['by' => $user->id, 'at' => now()->toIso8601String()]],
            ]);

            return SetStockDeliveryItemCheckedQuantity::make()->action($stockDeliveryItem, ['unit_quantity_checked' => $units]);
        });
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDeliveryItem
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $request->user(), $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, User $user, array $modelData): StockDeliveryItem
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
