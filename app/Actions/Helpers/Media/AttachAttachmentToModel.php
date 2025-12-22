<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
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
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachAttachmentToModel extends OrgAction
{
    public function handle(Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn|Product|TradeUnitFamily $model, array $modelData): void
    {
        foreach (Arr::get($modelData, 'attachments') as $attachment) {
            $file           = $attachment;
            $attachmentData = [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'scope'        => Arr::get($modelData, 'scope', 'Other'),
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'extension'    => $file->getClientOriginalExtension()
            ];

            SaveModelAttachment::make()->action($model, $attachmentData);
        }

        if ($model instanceof TradeUnit || $model instanceof TradeUnitFamily) {
            if ($model instanceof TradeUnitFamily) {
                foreach ($model->tradeUnits as $tradeUnit) {
                    foreach ($tradeUnit->products as $product) {
                        CloneProductAttachmentsFromTradeUnits::run($product);
                    }
                }
            } else {
                foreach ($model->products as $product) {
                    CloneProductAttachmentsFromTradeUnits::run($product);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:50000'],
            'scope'      => [
                'required',
                'string'
            ],
        ];
    }

    public function inTradeUnitFamily(TradeUnitFamily $tradeUnitFamily, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnitFamily->group, $request);

        $this->handle($tradeUnitFamily, $this->validatedData);
    }

    public function inProduct(Product $product, ActionRequest $request): void
    {
        $this->initialisation($product->organisation, $request);

        $this->handle($product, $this->validatedData);
    }

    public function inEmployee(Employee $employee, ActionRequest $request): void
    {
        $this->initialisation($employee->organisation, $request);

        $this->handle($employee, $this->validatedData);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): void
    {
        $this->initialisationFromGroup($supplier->group, $request);

        $this->handle($supplier, $this->validatedData);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): void
    {
        $this->initialisation($customer->organisation, $request);

        $this->handle($customer, $this->validatedData);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, ActionRequest $request): void
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        $this->handle($purchaseOrder, $this->validatedData);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->initialisation($stockDelivery->organisation, $request);

        $this->handle($stockDelivery, $this->validatedData);
    }

    public function inOrder(Order $order, ActionRequest $request): void
    {
        $this->initialisation($order->organisation, $request);

        $this->handle($order, $this->validatedData);
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->initialisation($palletDelivery->organisation, $request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function inPalletReturn(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->initialisation($palletReturn->organisation, $request);

        $this->handle($palletReturn, $this->validatedData);
    }
}
