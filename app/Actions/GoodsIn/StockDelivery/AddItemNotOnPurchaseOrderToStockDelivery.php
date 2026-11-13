<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplierProducts\ResolveOrgStockForSupplierProduct;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
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
 * left as they are: the delivery is what costs the stock.
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
            ->where('organisation_id', $stockDelivery->organisation_id)
            ->where('state', OrgSupplierProductStateEnum::ACTIVE->value)
            ->when($parentColumn, fn (Builder $query) => $query->where($parentColumn, $stockDelivery->parent_id), fn (Builder $query) => $query->whereRaw('false'));
    }

    public static function acceptsItems(StockDelivery $stockDelivery): bool
    {
        return in_array($stockDelivery->parent_type, ['OrgSupplier', 'OrgAgent'], true)
            && !$stockDelivery->is_costed
            && ($stockDelivery->isInGoodsIn() || $stockDelivery->state === StockDeliveryStateEnum::BOOKED_IN);
    }

    public function rules(): array
    {
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
            $validator->errors()->add('org_supplier_product_id', __('The delivery is costed: reopen the costing before adding items'));
        } elseif (!self::acceptsItems($this->stockDelivery)) {
            $validator->errors()->add('org_supplier_product_id', __('Items can only be added while the delivery is being booked in'));
        }
    }

    public function handle(StockDelivery $stockDelivery, User $user, array $modelData): StockDeliveryItem
    {
        $orgSupplierProduct = OrgSupplierProduct::findOrFail($modelData['org_supplier_product_id']);
        $supplierProduct    = $orgSupplierProduct->supplierProduct;
        $orgStock           = ResolveOrgStockForSupplierProduct::run($stockDelivery->organisation, $supplierProduct);

        if (!$orgStock) {
            throw ValidationException::withMessages(['org_supplier_product_id' => __(':code is not linked to any of our SKOs', ['code' => $supplierProduct->code])]);
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
