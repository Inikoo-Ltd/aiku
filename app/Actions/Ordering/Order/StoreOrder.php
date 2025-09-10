<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderIntervals;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\Ordering\Order\Search\OrderRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderIntervals;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderPayDetailedStatusEnum;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use App\Rules\IUnique;
use App\Rules\ValidAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreOrder extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithOrderExchanges;
    use WithNoStrictRules;

    public int $hydratorsDelay = 0;

    private CustomerClient|Customer $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Shop|Customer|CustomerClient $parent, array $modelData): Order
    {
        if (!Arr::get($modelData, 'reference')) {
            data_set(
                $modelData,
                'reference',
                GetSerialReference::run(
                    container: $parent->shop,
                    modelType: SerialReferenceModelEnum::ORDER
                )
            );
        }
        data_set($modelData, 'date', now(), overwrite: false);

        if ($this->strict) {
            $modelData['pay_status']          = OrderPayStatusEnum::UNPAID->value;
            $modelData['pay_detailed_status'] = OrderPayDetailedStatusEnum::UNPAID->value;
            if ($parent instanceof Customer) {
                data_forget($modelData, 'billing_address'); // Just in case is added by mistake
                data_forget($modelData, 'delivery_address'); // Just in case is added by mistake
                $billingAddress  = $parent->address;
                $deliveryAddress = $parent->deliveryAddress;
            } elseif ($parent instanceof CustomerClient) {
                data_forget($modelData, 'billing_address'); // Just in case is added by mistake
                $billingAddress  = $parent->customer->address;
                $deliveryAddress = Arr::pull($modelData, 'delivery_address') ?? $parent->address;
            } else {
                $billingAddress  = Arr::pull($modelData, 'billing_address');
                $deliveryAddress = Arr::pull($modelData, 'delivery_address');
            }
        } else {
            $billingAddress  = Arr::pull($modelData, 'billing_address');
            $deliveryAddress = Arr::pull($modelData, 'delivery_address');
        }

        if ($parent instanceof Customer) {
            $modelData['customer_id'] = $parent->id;
            $modelData['currency_id'] = $parent->shop->currency_id;
            $modelData['shop_id']     = $parent->shop_id;
            $shop                     = $parent->shop;
        } elseif ($parent instanceof CustomerClient) {
            $modelData['customer_id']               = $parent->customer_id;
            $modelData['customer_client_id']        = $parent->id;
            $modelData['currency_id']               = $parent->shop->currency_id;
            $modelData['shop_id']                   = $parent->shop_id;
            $modelData['platform_id']               = $parent->salesChannel->platform_id;
            $modelData['customer_sales_channel_id'] = $parent->customer_sales_channel_id;
            $shop                                   = $parent->shop;
        } else {
            $modelData['currency_id'] = $parent->currency_id;
            $modelData['shop_id']     = $parent->id;
            $shop                     = $parent;
        }

        data_set($modelData, 'master_shop_id', $shop->master_shop_id);


        if (!Arr::exists($modelData, 'tax_category_id')) {
            if ($parent instanceof Shop) {
                $taxNumber = null;
            } elseif ($parent instanceof Customer) {
                $taxNumber = $parent->taxNumber;
            } else {
                $taxNumber = $parent->customer->taxNumber;
            }

            data_set(
                $modelData,
                'tax_category_id',
                GetTaxCategory::run(
                    country: $this->organisation->country,
                    taxNumber: $taxNumber,
                    billingAddress: $billingAddress,
                    deliveryAddress: $deliveryAddress
                )->id
            );
        }


        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);


        $modelData = $this->processExchanges($modelData, $parent->shop);


        if ($this->strict) {
            data_set($modelData, 'customer_locked', true);
            data_set($modelData, 'billing_locked', true);
            data_set($modelData, 'delivery_locked', true);
        }

        $order = DB::transaction(function () use ($modelData, $billingAddress, $deliveryAddress, $shop) {
            $order = Order::create($modelData);
            $order->refresh();
            $order->stats()->create();

            if ($shop->masterShop) {
                $shop->masterShop->orderingStats->update(
                    [
                        'last_order_created_at' => now()
                    ]
                );
            }

            if ($order->billing_locked) {
                $this->createFixedAddress(
                    $order,
                    $billingAddress,
                    'Ordering',
                    'billing',
                    'billing_address_id'
                );
            } else {
                StoreOrderAddress::make()->action(
                    $order,
                    [
                        'address' => $billingAddress,
                        'type'    => 'billing'
                    ]
                );
            }


            if ($order->handing_type == OrderHandingTypeEnum::SHIPPING) {
                if ($order->delivery_locked) {
                    $this->createFixedAddress(
                        $order,
                        $deliveryAddress,
                        'Ordering',
                        'delivery',
                        'delivery_address_id'
                    );
                } else {
                    StoreOrderAddress::make()->action(
                        $order,
                        [
                            'address' => $deliveryAddress,
                            'type'    => 'delivery'
                        ]
                    );
                }
            } else {
                $order->updateQuietly(
                    [
                        'collection_address_id' => $order->shop->collection_address_id,
                        'delivery_country_id'   => $order->shop->collectionAddress->country_id
                    ]
                );
            }

            return $order;
        });

        $this->orderHydrators($order);

        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        ShopHydrateOrderIntervals::dispatch($order->shop, $intervalsExceptHistorical, []);
        OrganisationHydrateOrderIntervals::dispatch($order->organisation, $intervalsExceptHistorical, []);
        GroupHydrateOrderIntervals::dispatch($order->group, $intervalsExceptHistorical, []);

        GroupHydrateOrderInBasketAtCreatedIntervals::dispatch($order->group, $intervalsExceptHistorical, []);
        OrganisationHydrateOrderInBasketAtCreatedIntervals::dispatch($order->organisation, $intervalsExceptHistorical, []);
        ShopHydrateOrderInBasketAtCreatedIntervals::dispatch($order->shop, $intervalsExceptHistorical, []);

        if ($order->master_shop_id) {
            MasterShopHydrateOrderInBasketAtCreatedIntervals::dispatch($order->master_shop_id, $intervalsExceptHistorical, []);
        }

        if ($order->updated_by_customer_at) {
            GroupHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch($order->group, $intervalsExceptHistorical, []);
            OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch($order->organisation, $intervalsExceptHistorical, []);
            ShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch($order->shop, $intervalsExceptHistorical, []);
            if ($order->master_shop_id) {
                MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch($order->master_shop_id, $intervalsExceptHistorical, []);
            }
        }

        if ($order->customer_client_id) {
            CustomerClientHydrateOrders::dispatch($order->customerClient)->delay($this->hydratorsDelay);
        }


        if ($order->state == OrderStateEnum::CREATING) {
            if ($order->customer_client_id) {
                $order->customerClient()->update([
                    'amount_in_basket'           => $order->total_amount,
                    'current_order_in_basket_id' => $order->id
                ]);
            } else {
                $order->customer()->update([
                    'amount_in_basket'           => $order->total_amount,
                    'current_order_in_basket_id' => $order->id
                ]);
            }
        }


        OrderRecordSearch::dispatch($order);

        return $order->fresh();
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'max:64',
                'string',
                new IUnique(
                    table: 'orders',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),
            ],


            'customer_reference'        => ['sometimes', 'string', 'max:255'],
            'state'                     => ['sometimes', Rule::enum(OrderStateEnum::class)],
            'status'                    => ['sometimes', Rule::enum(OrderStatusEnum::class)],
            'handing_type'              => ['sometimes', 'required', Rule::enum(OrderHandingTypeEnum::class)],
            'tax_category_id'           => ['sometimes', 'required', 'exists:tax_categories,id'],
            'platform_id'               => ['sometimes', 'nullable', 'integer'],
            'platform_order_id'         => ['sometimes', 'nullable'],
            'customer_client_id'        => ['sometimes', 'nullable', 'exists:customer_clients,id'],
            'customer_sales_channel_id' => ['sometimes', 'nullable', 'integer'],
            'data'                      => ['sometimes', 'array'],
            'sales_channel_id'          => [
                'sometimes',
                'required',
                Rule::exists('sales_channels', 'id')->where(function ($query) {
                    $query->where('group_id', $this->shop->group_id);
                })
            ],
            'billing_address'           => ['sometimes', 'required', new ValidAddress()], // only need when parent is Shop
            'delivery_address'          => ['sometimes', 'required', new ValidAddress()],  // only need when the parent is Shop|CustomerClient


        ];

        if (!$this->strict) {
            $rules['billing_address']  = ['required', new ValidAddress()];
            $rules['delivery_address'] = ['required', new ValidAddress()];

            $rules = $this->orderNoStrictFields($rules);
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function prepareForValidation(): void
    {
        if ($this->get('handing_type') == OrderHandingTypeEnum::COLLECTION && !$this->shop->collection_address_id) {
            abort(400, 'Collection orders require a collection address');
        }

        if ($this->get('handing_type') == OrderHandingTypeEnum::COLLECTION && !$this->shop->collectionAddress->country_id) {
            abort(400, 'Invalid collection address');
        }
    }

    public function htmlResponse(Order $order, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.customer.order.store' => Redirect::route('grp.org.shops.show.crm.customers.show.orders.show', [
                $order->organisation->slug,
                $order->shop->slug,
                $order->customer->slug,
                $order->slug
            ]),
            'grp.models.customer_client.order.store' => Redirect::route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show.orders.show', [
                $order->organisation->slug,
                $order->shop->slug,
                $order->customer->slug,
                $order->customerSalesChannel->slug,
                $order->customerClient->ulid,
                $order->slug
            ]),
            'grp.models.customer_client.order' => Redirect::route('grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show', [
                $order->organisation->slug,
                $order->shop->slug,
                $order->customer->slug,
                $order->platform->slug,
                $order->slug
            ]),
        };
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop|Customer|CustomerClient $parent, array $modelData, bool $strict = true, int $hydratorsDelay = 60, $audit = true): Order
    {
        if (!$audit) {
            Order::disableAuditing();
        }

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

    /**
     * @throws \Throwable
     */
    public function inCustomer(Customer $customer, ActionRequest $request): Order
    {
        $this->parent = $customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inPlatformCustomer(Customer $customer, Platform $platform, ActionRequest $request): Order
    {
        $this->parent = $customer;
        $this->set('platform_id', $platform->id);
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inPlatformCustomerClient(CustomerClient $customerClient, Platform $platform, ActionRequest $request): Order
    {
        $this->parent = $customerClient;
        $this->set('platform_id', $platform->id);
        $this->initialisationFromShop($customerClient->shop, $request);

        return $this->handle($customerClient, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inCustomerClient(CustomerClient $customerClient, ActionRequest $request): Order
    {
        $this->parent = $customerClient;
        $this->initialisationFromShop($customerClient->shop, $request);

        return $this->handle($customerClient, $this->validatedData);
    }


}
