<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\GoodsIn\StockDelivery;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;

trait WithAttachmentEditAuthorisation
{
    use WithComplianceEditing;

    protected function canChangeAttachments(?User $user, Model $model): bool
    {
        if (!$user) {
            return false;
        }

        if ($model instanceof TradeUnit || $model instanceof TradeUnitFamily || $model instanceof Product) {
            return $this->canChangeComplianceDocuments($user, $model);
        }

        $permissions = $this->attachmentEditPermissions($model);

        return $permissions && $user->authTo($permissions);
    }

    private function attachmentEditPermissions(Model $model): array
    {
        return match (true) {
            $model instanceof Employee => [
                "human-resources.$model->organisation_id.edit",
                "org-supervisor.$model->organisation_id.human-resources",
            ],
            $model instanceof Agent => ['supply-chain.edit'],
            $model instanceof Supplier => [
                'supply-chain.edit',
                ...collect($model->agent ? [$model->agent->organisation_id] : $model->orgSuppliers()->pluck('organisation_id'))
                    ->map(fn ($organisationId) => "procurement.$organisationId.edit")
                    ->all(),
            ],
            $model instanceof PurchaseOrder, $model instanceof StockDelivery => ["procurement.$model->organisation_id.edit"],
            $model instanceof Customer => $model->shop->type == ShopTypeEnum::FULFILMENT
                ? ["fulfilment-shop.{$model->shop->fulfilment?->id}.edit"]
                : ["crm.$model->shop_id.edit"],
            $model instanceof Order => ["orders.$model->shop_id.edit"],
            $model instanceof Invoice => array_filter([
                "accounting.$model->organisation_id.edit",
                "crm.$model->shop_id.edit",
                "orders.$model->shop_id.edit",
                $model->shop->fulfilment ? "fulfilment-shop.{$model->shop->fulfilment->id}.edit" : null,
            ]),
            $model instanceof PalletDelivery => [
                "fulfilment-shop.$model->fulfilment_id.edit",
                "supervisor-fulfilment-shop.$model->fulfilment_id",
            ],
            $model instanceof PalletReturn => [
                "fulfilment-shop.$model->fulfilment_id.edit",
                "fulfilment.$model->warehouse_id.edit",
                "supervisor-incoming.$model->warehouse_id",
                "supervisor-fulfilment.$model->warehouse_id",
            ],
            $model instanceof ProductCategory => ["products.$model->shop_id.edit"],
            default => [],
        };
    }
}
