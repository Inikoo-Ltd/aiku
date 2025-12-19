<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Action to store a new Return for customer order returns
 */

namespace App\Actions\Dispatching\Return;

use App\Actions\OrgAction;
use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Models\Dispatching\OrderReturn;
use App\Models\Dispatching\ReturnStats;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreReturn extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private Order $order;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): OrderReturn
    {
        $returnAddress = Arr::pull($modelData, 'return_address', $order->deliveryAddress);

        data_set($modelData, 'date', now());
        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'customer_client_id', $order->customer_client_id);
        data_set($modelData, 'platform_id', $order->platform_id);
        data_set($modelData, 'customer_sales_channel_id', $order->customer_sales_channel_id);

        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);
        data_set($modelData, 'state', ReturnStateEnum::WAITING_TO_RECEIVE);

        data_set($modelData, 'email', $order->customer->email ?? null);
        data_set($modelData, 'phone', $order->customer->phone ?? null);

        $items = Arr::pull($modelData, 'return_items', []);

        $orderReturn = DB::transaction(function () use ($order, $modelData, $returnAddress, $items) {
            /** @var OrderReturn $orderReturn */
            $orderReturn = OrderReturn::create($modelData);

            // Attach the order
            $orderReturn->orders()->attach($order->id);

            // Create address from return address if provided
            if ($returnAddress instanceof Address) {
                $addressData = $returnAddress->only([
                    'address_line_1',
                    'address_line_2',
                    'sorting_code',
                    'postal_code',
                    'dependent_locality',
                    'locality',
                    'administrative_area',
                    'country_code',
                    'country_id',
                ]);
                $addressData['group_id'] = $orderReturn->group_id;
                $newAddress = Address::create($addressData);
                $orderReturn->updateQuietly([
                    'address_id' => $newAddress->id,
                    'return_country_id' => $newAddress->country_id ?? null,
                ]);
            }

            // Create stats
            ReturnStats::create([
                'return_id' => $orderReturn->id,
            ]);

            // If no items provided, automatically copy from order's delivery notes
            if (empty($items)) {
                $items = $this->getItemsFromOrder($order);
            }

            // Create return items
            $itemCount = 0;
            foreach ($items as $itemData) {
                StoreReturnItem::make()->action($orderReturn, $itemData);
                $itemCount++;
            }

            // Update item count
            $orderReturn->updateQuietly(['number_items' => $itemCount]);

            return $orderReturn;
        });

        $orderReturn->refresh();

        return $orderReturn;
    }

    /**
     * Get items from order's dispatched delivery notes
     */
    protected function getItemsFromOrder(Order $order): array
    {
        $items = [];

        // Get items from dispatched delivery notes
        foreach ($order->deliveryNotes as $deliveryNote) {
            foreach ($deliveryNote->deliveryNoteItems as $dnItem) {
                $items[] = [
                    'delivery_note_item_id' => $dnItem->id,
                    'org_stock_id'          => $dnItem->org_stock_id,
                    'quantity_expected'     => $dnItem->quantity_dispatched ?? $dnItem->quantity_required ?? 1,
                ];
            }
        }

        // Fallback: if no delivery note items, get from order transactions
        if (empty($items)) {
            foreach ($order->transactions as $transaction) {
                $items[] = [
                    'transaction_id'    => $transaction->id,
                    'org_stock_id'      => $transaction->org_stock_id ?? null,
                    'quantity_expected' => $transaction->quantity_ordered ?? 1,
                ];
            }
        }

        return $items;
    }

    public function htmlResponse(OrderReturn $orderReturn): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.incoming.returns.show', [
            'organisation' => $orderReturn->organisation->slug,
            'warehouse'    => $orderReturn->warehouse->slug,
            'return'       => $orderReturn->slug,
        ]);
    }

    public function rules(): array
    {
        return [
            'return_items'  => ['nullable', 'array'],
            'warehouse_id'  => ['required', 'integer'],
            'reference'     => ['required', 'max:64', 'string'],
            'return_reason' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrderReturn
    {
        if (! $audit) {
            OrderReturn::disableAuditing();
        }
        $this->asAction = true;
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): OrderReturn
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (! $this->has('warehouse_id')) {
            /** @var Warehouse $warehouse */
            $warehouse = $this->shop->organisation->warehouses()->first();
            $this->set('warehouse_id', $warehouse->id);
        }

        if (! $this->has('reference')) {
            $baseReference = $this->order->reference.'-RET';
            $existingRefs = OrderReturn::whereHas('orders', function ($query) {
                $query->where('orders.id', $this->order->id);
            })
                ->pluck('reference')
                ->filter(function ($ref) use ($baseReference) {
                    return str_starts_with($ref, $baseReference);
                })
                ->map(function ($ref) use ($baseReference) {
                    return (int) str_replace($baseReference, '', $ref);
                })
                ->filter(function ($num) {
                    return $num > 0;
                });

            $nextIncrement = ($existingRefs->max() ?? 0) + 1;

            $this->set('reference', $baseReference.$nextIncrement);
        }
    }
}
