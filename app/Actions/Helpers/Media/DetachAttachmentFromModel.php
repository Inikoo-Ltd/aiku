<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Oct 2024 11:00:03 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Actions\Catalogue\Product\CloneProductAttachmentsFromTradeUnits;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
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
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Actions\Traits\Authorisations\WithAttachmentEditAuthorisation;
use Lorisleiva\Actions\Concerns\AsAction;

class DetachAttachmentFromModel extends OrgAction
{
    use AsAction;
    use WithAttachmentEditAuthorisation;

    public function handle(Employee|TradeUnit|Agent|Supplier|Customer|PurchaseOrder|StockDelivery|Order|Invoice|PalletDelivery|PalletReturn|TradeUnitFamily|Product|ProductCategory $model, Media $attachment): Employee|TradeUnit|Agent|Supplier|Customer|PurchaseOrder|StockDelivery|Order|Invoice|PalletDelivery|PalletReturn|TradeUnitFamily|Product|ProductCategory
    {
        $model->attachments()->detach($attachment->id);
        $model->refresh();
        if ($model instanceof TradeUnit) {
            foreach ($model->products as $product) {
                CloneProductAttachmentsFromTradeUnits::run($product);
            }
        }
        if ($model instanceof TradeUnitFamily) {
            foreach ($model->tradeUnits as $tradeUnit) {
                foreach ($tradeUnit->products as $product) {
                    CloneProductAttachmentsFromTradeUnits::run($product);
                }
            }
        }

        return $model;
    }


    public function action(Employee|TradeUnit|Agent|Supplier|Customer|PurchaseOrder|StockDelivery|Order|TradeUnitFamily|ProductCategory $model, Media $attachment, int $hydratorsDelay = 0, bool $strict = true): Employee|TradeUnit|Agent|Supplier|Customer|PurchaseOrder|StockDelivery|Order|TradeUnitFamily|ProductCategory
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;


        $this->initialisationFromGroup($model->group, []);

        return $this->handle($model, $attachment);
    }


    public function inTradeUnitFamily(TradeUnitFamily $tradeUnitFamily, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $tradeUnitFamily), 403);
        $this->initialisationFromGroup($tradeUnitFamily->group, []);
        $this->handle($tradeUnitFamily, $attachment);
    }

    public function inEmployee(Employee $employee, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $employee), 403);
        $this->initialisation($employee->organisation, []);
        $this->handle($employee, $attachment);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $tradeUnit), 403);
        $this->initialisationFromGroup($tradeUnit->group, []);
        $this->handle($tradeUnit, $attachment);
    }

    public function inAgent(Agent $agent, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $agent), 403);
        $this->initialisationFromGroup($agent->group, []);
        $this->handle($agent, $attachment);
    }

    public function inSupplier(Supplier $supplier, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $supplier), 403);
        $this->initialisationFromGroup($supplier->group, []);
        $this->handle($supplier, $attachment);
    }

    public function inCustomer(Customer $customer, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $customer), 403);
        $this->initialisation($customer->organisation, []);
        $this->handle($customer, $attachment);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $purchaseOrder), 403);
        $this->initialisation($purchaseOrder->organisation, []);
        $this->handle($purchaseOrder, $attachment);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $stockDelivery), 403);
        $this->initialisation($stockDelivery->organisation, []);
        $this->handle($stockDelivery, $attachment);
    }

    public function inOrder(Order $order, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $order), 403);
        $this->initialisation($order->organisation, []);
        $this->handle($order, $attachment);
    }

    public function inInvoice(Invoice $invoice, Media $attachment)
    {
        abort_unless($this->canChangeAttachments(request()->user(), $invoice), 403);
        $this->initialisation($invoice->organisation, []);
        $this->handle($invoice, $attachment);
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, Media $attachment): void
    {
        abort_unless($this->canChangeAttachments(request()->user(), $palletDelivery), 403);
        $this->initialisation($palletDelivery->organisation, []);

        $this->handle($palletDelivery, $attachment);
    }

    public function inPalletReturn(PalletReturn $palletReturn, Media $attachment): void
    {
        abort_unless($this->canChangeAttachments(request()->user(), $palletReturn), 403);
        $this->initialisation($palletReturn->organisation, []);

        $this->handle($palletReturn, $attachment);
    }
}
