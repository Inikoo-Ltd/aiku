<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Oct 2024 11:00:03 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Catalogue\Product\CloneProductAttachmentsFromTradeUnits;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\Concerns\AsAction;

class DetachAttachmentFromModel extends OrgAction
{
    use AsAction;

    public function handle(Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn|TradeUnitFamily|Product $model, Media $attachment): Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn|TradeUnitFamily|Product
    {
        $model->attachments()->detach($attachment->id);
        $model->refresh();
        if ($model instanceof TradeUnit) {
            foreach ($model->products as $product) {
                CloneProductAttachmentsFromTradeUnits::run($product);
            }
        }
        return $model;
    }


    public function action(
        Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order $model,
        Media $attachment,
        int $hydratorsDelay = 0,
        bool $strict = true
    ): Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromGroup($model->group, []);

        return $this->handle($model, $attachment);
    }


    public function inProduct(Product $product, Media $attachment)
    {
        $this->initialisation($product->organisation, []);
        $this->handle($product, $attachment);
    }

    public function inTradeUnitFamily(TradeUnitFamily $tradeUnitFamily, Media $attachment)
    {
        $this->initialisationFromGroup($tradeUnitFamily->group, []);
        $this->handle($tradeUnitFamily, $attachment);
    }

    public function inEmployee(Employee $employee, Media $attachment)
    {
        $this->initialisation($employee->organisation, []);
        $this->handle($employee, $attachment);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Media $attachment)
    {
        $this->initialisationFromGroup($tradeUnit->group, []);
        $this->handle($tradeUnit, $attachment);
    }

    public function inSupplier(Supplier $supplier, Media $attachment)
    {
        $this->initialisationFromGroup($supplier->group, []);
        $this->handle($supplier, $attachment);
    }

    public function inCustomer(Customer $customer, Media $attachment)
    {
        $this->initialisation($customer->organisation, []);
        $this->handle($customer, $attachment);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, Media $attachment)
    {
        $this->initialisation($purchaseOrder->organisation, []);
        $this->handle($purchaseOrder, $attachment);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, Media $attachment)
    {
        $this->initialisation($stockDelivery->organisation, []);
        $this->handle($stockDelivery, $attachment);
    }

    public function inOrder(Order $order, Media $attachment)
    {
        $this->initialisation($order->organisation, []);
        $this->handle($order, $attachment);
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, Media $attachment): void
    {
        $this->initialisation($palletDelivery->organisation, []);

        $this->handle($palletDelivery, $attachment);
    }

    public function inPalletReturn(PalletReturn $palletReturn, Media $attachment): void
    {
        $this->initialisation($palletReturn->organisation, []);

        $this->handle($palletReturn, $attachment);
    }
}
