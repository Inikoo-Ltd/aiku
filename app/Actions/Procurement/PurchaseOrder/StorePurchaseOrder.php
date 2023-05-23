<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:26:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\Agent\Hydrators\AgentHydratePurchaseOrders;
use App\Actions\Procurement\Supplier\Hydrators\SupplierHydratePurchaseOrders;
use App\Actions\Tenancy\Tenant\Hydrators\TenantHydrateProcurement;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\SupplierProduct\SupplierProductStateEnum;
use App\Models\Procurement\Agent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePurchaseOrder
{
    use AsAction;
    use WithAttributes;

    private bool $force;

    private Supplier|Agent $parent;

    public function handle(Agent|Supplier $parent, array $modelData): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $parent->purchaseOrders()->create([
            'number' => rand(1111, 9999),
            'date' => now(),
            'currency_id' => $parent->currency_id
        ]);

        if(class_basename($parent) == 'Supplier') {
            SupplierHydratePurchaseOrders::dispatch($parent);
        } else {
            AgentHydratePurchaseOrders::dispatch($parent);
        }

        TenantHydrateProcurement::dispatch(app('currentTenant'));

//        return redirect()->route('procurement.purchase-orders.show', $purchaseOrder->slug);
        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'number'        => ['sometimes', 'required', 'numeric', 'unique:group.purchase_orders'],
            'date'          => ['sometimes', 'required', 'date'],
            'currency_id'   => ['sometimes', 'required', 'exists:currencies,id'],
            'exchange'      => ['sometimes', 'required', 'numeric']
        ];
    }

     public function afterValidator(Validator $validator): void
     {
         $numberPurchaseOrdersStateCreating = $this->parent->purchaseOrders()->where('state', PurchaseOrderStateEnum::CREATING)->count();

         if(!$this->force && $numberPurchaseOrdersStateCreating>= 1) {
             $validator->errors()->add('purchase_order', 'Are you sure want to create new purchase order?');
         }

         if($this->parent->products->where('state', '<>', SupplierProductStateEnum::DISCONTINUED)->count() == 0) {
             $message = match (class_basename($this->parent)) {
                 'Agent'    => 'You can not create purchase order if the agent dont have any product',
                 'Supplier' => 'You can not create purchase order if the supplier dont have any product',
             };
             $validator->errors()->add('purchase_order', $message);
         }
     }

    public function action(Agent|Supplier $parent, array $objectData, bool $force = false):  PurchaseOrder
    {
        $this->parent = $parent;
        $this->force  = $force;
        $this->setRawAttributes($objectData);
        $validatedData = $this->validateAttributes();

        return $this->handle($parent, $validatedData);
    }

    public function inAgent(Agent $agent, ActionRequest $request):  RedirectResponse|PurchaseOrder
    {
        $this->force  = false;
        $this->parent = $agent;
        $request->validate();

        return $this->handle($agent, $request->all());
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request):  RedirectResponse|PurchaseOrder
    {
        $this->force  = false;
        $this->parent = $supplier;
        $request->validate();

        return $this->handle($supplier, $request->all());
    }
}
