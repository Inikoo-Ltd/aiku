<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateUniversalSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrder extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithFixedAddressActions;
    use WithModelAddressActions;

    public int $hydratorsDelay = 0;

    public function handle(
        Shop|Customer|CustomerClient $parent,
        array $modelData,
    ): Order {
        $billingAddress = $modelData['billing_address'];
        data_forget($modelData, 'billing_address');
        /** @var Address $deliveryAddress */
        $deliveryAddress = Arr::get($modelData, 'delivery_address');
        data_forget($modelData, 'delivery_address');


        if (class_basename($parent) == 'Customer') {
            $modelData['customer_id'] = $parent->id;
            $modelData['currency_id'] = $parent->shop->currency_id;
            $modelData['shop_id']     = $parent->shop_id;
        } elseif (class_basename($parent) == 'CustomerClient') {
            $modelData['customer_id']        = $parent->customer_id;
            $modelData['customer_client_id'] = $parent->id;
            $modelData['currency_id']        = $parent->shop->currency_id;
            $modelData['shop_id']            = $parent->shop_id;
        } else {
            $modelData['currency_id'] = $parent->currency_id;
            $modelData['shop_id']     = $parent->id;
        }

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);


        /** @var Order $order */
        $order = Order::create($modelData);
        $order->refresh();
        $order->stats()->create();

        if ($order->billing_locked) {
            $order = $this->createFixedAddress($order, $billingAddress, 'Ordering', 'billing', 'billing_address_id');
        } else {
            $order = $this->addAddressToModel(
                model: $order,
                addressData: $billingAddress->toArray(),
                scope: 'billing',
                updateLocation: false,
                updateAddressField: 'billing_address_id'
            );
        }

        $order->updateQuietly(
            [
                'billing_country_id' => $order->billingAddress->country_id
            ]
        );


        if ($order->handing_type == OrderHandingTypeEnum::SHIPPING) {
            if ($order->delivery_locked) {
                $order = $this->createFixedAddress($order, $deliveryAddress, 'Ordering', 'delivery', 'delivery_address_id');
            } else {
                $order = $this->addAddressToModel(
                    model: $order,
                    addressData: $deliveryAddress->toArray(),
                    scope: 'delivery',
                    updateLocation: false,
                    updateAddressField: 'delivery_address_id'
                );
            }
            $order->updateQuietly(
                [
                    'delivery_country_id' => $order->deliveryAddress->country_id
                ]
            );
        } else {
            $order->updateQuietly(
                [
                    'collection_address_id' => $order->shop->collection_address_id,
                    'delivery_country_id'   => $order->shop->collectionAddress->country_id
                ]
            );
        }


        HydrateOrder::make()->originalItems($order);

        GroupHydrateOrders::dispatch($order->shop->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOrders::dispatch($order->shop->organisation)->delay($this->hydratorsDelay);
        if (class_basename($parent) == 'Shop') {
            ShopHydrateOrders::dispatch($parent)->delay($this->hydratorsDelay);
        } else {
            ShopHydrateOrders::dispatch($parent->shop)->delay($this->hydratorsDelay);
        }
        OrderHydrateUniversalSearch::dispatch($order);

        return $order->fresh();
    }

    public function rules(): array
    {
        $rules = [
            'number'          => [
                'required',
                'max:64',
                'string',
                new IUnique(
                    table: 'orders',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],
            'date'            => ['required', 'date'],
            'submitted_at'    => ['sometimes', 'nullable', 'date'],
            'in_warehouse_at' => ['sometimes', 'nullable', 'date'],
            'packed_at'       => ['sometimes', 'nullable', 'date'],
            'finalised_at'    => ['sometimes', 'nullable', 'date'],
            'dispatched_at'   => ['sometimes', 'nullable', 'date'],
            'customer_number' => ['sometimes', 'string', 'max:64'],
            'state'           => ['sometimes', Rule::enum(OrderStateEnum::class)],
            'status'          => ['sometimes', Rule::enum(OrderStatusEnum::class)],
            'handing_type'    => ['sometimes', 'required', Rule::enum(OrderHandingTypeEnum::class)],

            'created_at'   => ['sometimes', 'required', 'date'],
            'cancelled_at' => ['sometimes', 'nullable', 'date'],

            'billing_address'  => ['required', new ValidAddress()],
            'delivery_address' => ['sometimes', 'required', new ValidAddress()],
            'billing_locked'   => ['sometimes', 'boolean'],
            'delivery_locked'  => ['sometimes', 'boolean'],

            'source_id' => ['sometimes', 'string', 'max:64'],


        ];

        if (!$this->strict) {
            $rules['number'] = ['sometimes', 'string', 'max:64'];
        }

        return $rules;
    }


    public function action(
        Shop|Customer|CustomerClient $parent,
        array $modelData,
        bool $strict = true,
        int $hydratorsDelay = 60
    ): Order {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;


        $shop = match (class_basename($parent)) {
            'Shop' => $parent,
            'Customer', 'CustomerClient' => $parent->shop,
        };

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }


}
