<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-14h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Helpers\Media;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Goods\TradeUnit;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachAttachmentToModel extends OrgAction
{
    private Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn $parent;

    public function handle(Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn $model, array $modelData): void
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
    }

    public function rules(): array
    {
        // if ($this->parent instanceof Employee) {
        //     $allowedScopes = ['Other', 'CV', 'Contract'];
        // } elseif ($this->parent instanceof TradeUnit) {
        //     $allowedScopes = ['Other'];
        // } elseif ($this->parent instanceof Supplier) {
        //     $allowedScopes = ['Other'];
        // } elseif ($this->parent instanceof Customer) {
        //     $allowedScopes = ['Other'];
        // } elseif ($this->parent instanceof PurchaseOrder) {
        //     $allowedScopes = ['Other'];
        // } elseif ($this->parent instanceof StockDelivery) {
        //     $allowedScopes = ['Other'];
        // } elseif ($this->parent instanceof Order) {
        //     $allowedScopes = ['Other'];
        // } else {
        //     $allowedScopes = ['Other'];
        // }

        return [
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:50000'],
            'scope'      => [
                'required',
                'string'
            ],
        ];
    }

    public function inEmployee(Employee $employee, ActionRequest $request): void
    {
        $this->parent = $employee;
        $this->initialisation($employee->organisation, $request);

        $this->handle($employee, $this->validatedData);
    }

    public function inTradeUnit(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->parent = $tradeUnit;
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): void
    {
        $this->parent = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        $this->handle($supplier, $this->validatedData);
    }

    public function inCustomer(Customer $customer, ActionRequest $request): void
    {
        $this->parent = $customer;
        $this->initialisation($customer->organisation, $request);

        $this->handle($customer, $this->validatedData);
    }

    public function inPurchaseOrder(PurchaseOrder $purchaseOrder, ActionRequest $request): void
    {
        $this->parent = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        $this->handle($purchaseOrder, $this->validatedData);
    }

    public function inStockDelivery(StockDelivery $stockDelivery, ActionRequest $request): void
    {
        $this->parent = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        $this->handle($stockDelivery, $this->validatedData);
    }

    public function inOrder(Order $order, ActionRequest $request): void
    {
        $this->parent = $order;
        $this->initialisation($order->organisation, $request);

        $this->handle($order, $this->validatedData);
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->parent = $palletDelivery;
        $this->initialisation($palletDelivery->organisation, $request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function inPalletReturn(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->parent = $palletReturn;
        $this->initialisation($palletReturn->organisation, $request);

        $this->handle($palletReturn, $this->validatedData);
    }
}
