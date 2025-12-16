
test('store shipping country action', function () {
    // Ensure migration exists (in case snapshot DB was loaded)
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => Country::first()->id,
    ]);
    $this->shop->refresh();
    expect($shippingCountry)->toBeInstanceOf(ShippingCountry::class)
        ->and($this->shop->stats->number_shipping_countries)->toBe(1);
});



test('update shipping country action', function () {
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => 4,
    ]);
    expect($shippingCountry)->toBeInstanceOf(ShippingCountry::class);

    $updated = UpdateShippingCountry::make()->action($shippingCountry, [
        'territories' => ['A','B']
    ]);

    expect($updated->fresh()->territories)->toBe(['A','B']);
});

test('delete shipping country action dispatches hydrator and removes model', function () {
    $shippingCountry = StoreShippingCountry::make()->action($this->shop, [
        'country_id' => 6,
    ]);
    expect(ShippingCountry::query()->count())->toBeGreaterThanOrEqual(1);

    DeleteShippingCountry::make()->action($shippingCountry);

    expect(ShippingCountry::query()->whereKey($shippingCountry->id)->exists())->toBeFalse();
});


test('create order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);


    $order = StoreOrder::make()->action($this->customer, $modelData);

    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());
    $this->customer->refresh();

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->customer)->toBeInstanceOf(Customer::class)
        ->and($this->group->orderingStats->number_orders)->toBe(1)
        ->and($this->group->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->group->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->organisation->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->shop->orderingStats->number_orders)->toBe(1)
        ->and($this->shop->orderingStats->number_orders_state_creating)->toBe(1)
        ->and($this->shop->orderingStats->number_orders_handing_type_shipping)->toBe(1)
        ->and($this->customer->stats->number_orders)->toBe(1)
        ->and($this->customer->stats->number_orders_state_creating)->toBe(1)
        ->and($this->customer->stats->number_orders_handing_type_shipping)->toBe(1)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(0);

    return $order;
});

test('UI Edit Order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);

    $adminGuest = createAdminGuest($this->group);
    actingAs($adminGuest->getUser());

    $response = get(route(
        'grp.org.shops.show.ordering.orders.edit',
        [
            'organisation' => $this->organisation->slug,
            'shop' => $this->shop->slug,
            'order' => $order->slug,
        ]
    ));

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) use ($order) {
        $page
            ->component('EditModel')
            ->has('breadcrumbs')
            ->has('title')
            ->has('pageHead', function (AssertableInertia $head) use ($order) {
                $head->where('title', $order->slug)
                    ->has('actions', 1)
                    ->where('actions.0.style', 'exitEdit')
                    ->etc();
            })
            ->has('formData', function (AssertableInertia $form) use ($order) {
                $form->has('blueprint')
                    ->where('args.updateRoute.name', 'grp.models.order.update')
                    ->where('args.updateRoute.parameters.order', $order->id)
                    ->etc();
            });
    });
});

test('get order products', function (Order $order) {
    // Create a transaction if needed (may not be necessary if the order already has products)
    $order->transactions->first()
        ?: StoreTransaction::make()->action(
            $order,
            $this->product->historicAsset,
            Transaction::factory()->definition()
        );

    $order->refresh();


    // Test the GetOrderProducts action
    $result = GetOrderProducts::make()->handle($order);


    expect($result)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($result->count())->toBeGreaterThanOrEqual(1);

    // Test that the product data is correctly retrieved
    $products = $result->items();
    expect($products)->toBeArray()
        ->and(count($products))->toBeGreaterThanOrEqual(1);

    // Verify the first product data
    $firstProduct = $products[0];
    expect($firstProduct->id)->toBe(1)->and($firstProduct->transaction_id)->toBe(1);

    // Test the JSON response
    if (method_exists(GetOrderProducts::class, 'jsonResponse')) {
        $jsonResponse = GetOrderProducts::make()->jsonResponse($result);
        expect($jsonResponse)->toBeInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class);
    }

    return $order;
})->depends('create order');


test('delete previous transaction', function (Order $order) {
    $transaction = $order->transactions()->first();
    UpdateTransaction::make()->action(
        $transaction,
        ['quantity_ordered' => 0]
    );
    $order->refresh();
    expect($order->transactions()->count())->toBe(0)
        ->and($order->stats->number_item_transactions)->toBe(0)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(0);

    return $order;
})->depends('get order products');


test('create transaction', function ($order) {
    $transactionData = Transaction::factory()->definition();
    $historicAsset   = $this->product->historicAsset;
    expect($historicAsset)->toBeInstanceOf(HistoricAsset::class);
    $transaction = StoreTransaction::make()->action($order, $historicAsset, $transactionData);

    $order->refresh();


    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('delete previous transaction');

test('create transaction from adjustment', function (Order $order) {
    $adjustment = StoreAdjustment::make()->action(
        $order->shop,
        [
            'type'       => AdjustmentTypeEnum::CREDIT,
            'net_amount' => 10,
        ],
        strict: false
    );
    expect($adjustment)->toBeInstanceOf(Adjustment::class);
    $transaction = StoreTransactionFromAdjustment::make()->action($order, $adjustment, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1)
        ->and($order->shop->stats->number_adjustments)->toBe(1)
        ->and($order->shop->stats->number_adjustments_type_credit)->toBe(1)
        ->and($order->organisation->catalogueStats->number_adjustments)->toBe(1)
        ->and($order->group->catalogueStats->number_adjustments)->toBe(1);

    return $transaction;
})->depends('create order');

test('update adjustment', function () {
    $adjustment = StoreAdjustment::make()->action(
        $this->shop,
        [
            'type'       => AdjustmentTypeEnum::CREDIT,
            'net_amount' => 10,
        ],
        strict: false
    );
    expect($adjustment)->toBeInstanceOf(Adjustment::class);
    $updatedAdjustment = UpdateAdjustment::make()->action($adjustment, [
        'net_amount' => 20,
    ], strict: false);

    $updatedAdjustment->refresh();

    expect($updatedAdjustment)->toBeInstanceOf(Adjustment::class)
        ->and(intval($updatedAdjustment->net_amount))->toBe(20)
        ->and($updatedAdjustment->shop->stats->number_adjustments)->toBe(2);

    return $updatedAdjustment;
});

test('create transaction from charge', function (Order $order) {
    $charge = StoreCharge::make()->action($order->shop, [
        'code'        => 'charge-1',
        'name'        => 'charge 1',
        'description' => 'charge 1 description',
        'state'       => ChargeStateEnum::ACTIVE,
        'trigger'     => ChargeTriggerEnum::ORDER,
        'type'        => ChargeTypeEnum::TRACKING,
    ]);

    expect($charge)->toBeInstanceOf(Charge::class);
    $transaction = StoreTransactionFromCharge::make()->action($order, $charge, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('create order');

test('create transaction from shipping', function (Order $order) {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($order->shop, [
        'name' => 'schema 1',
    ]);
    $shipping           = StoreShippingZone::make()->action($shippingZoneSchema, [
        'code'        => 'SHIP-1',
        'name'        => 'shipping 1',
        'status'      => true,
        'price'       => [
            'type'  => "Step Order Items Net Amount",
            "steps" => [
                [
                    "to"    => 175,
                    "from"  => 0,
                    "price" => 20
                ],
                [
                    "to"    => 450,
                    "from"  => 175,
                    "price" => 40
                ],
                [
                    "to"    => 975,
                    "from"  => 450,
                    "price" => 60
                ],
                [
                    "to"    => "INF",
                    "from"  => 975,
                    "price" => 0
                ]
            ]
        ],
        'territories' => [
            [
                "country_code" => "FR"
            ],
            [
                "country_code" => "BE"
            ],
            [
                "country_code" => "LU"
            ]
        ],
        'position'    => 1,
        'is_failover' => false,
    ]);
    expect($shipping)->toBeInstanceOf(ShippingZone::class);
    $transaction = StoreTransactionFromShipping::make()->action($order, $shipping, [
        'date'             => Carbon::now(),
        'quantity_ordered' => 1,
    ]);

    $order->refresh();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $transaction;
})->depends('create order');

test('update transaction', function ($transaction) {
    $transaction = UpdateTransaction::make()->action(
        $transaction,
        [
            'quantity_ordered' => $transaction->quantity_ordered + 1,
        ]
    );

    expect($transaction)->toBeInstanceOf(Transaction::class);
})->depends('create transaction');


test('update order', function ($order) {
    $order = UpdateOrder::make()->action($order, Order::factory()->definition());

    $this->assertModelExists($order);
})->depends('create order');

test('update order state to submitted', function (Order $order) {
    $order = SubmitOrder::make()->action($order);
    expect($order->state)->toEqual(OrderStateEnum::SUBMITTED)
        ->and($order->shop->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->organisation->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->group->orderingStats->number_orders_state_submitted)->toBe(1)
        ->and($order->stats->number_item_transactions)->toBe(1);

    return $order;
})->depends('create order');

test('update order state to in warehouse', function (Order $order) {
    $deliveryNote = SendOrderToWarehouse::make()->action($order, []);
    $order->refresh();
    expect($deliveryNote)->toBeInstanceOf(DeliveryNote::class)
        ->and($order->state)->toEqual(OrderStateEnum::IN_WAREHOUSE);

    return $order;
})->depends('update order state to submitted');

test('update order state to Handling', function (Order $order) {
    $order = UpdateOrderStateToHandling::make()->action($order);
    $order->refresh();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toEqual(OrderStateEnum::HANDLING);

    return $order;
})->depends('update order state to in warehouse');

test('update order state to Packed ', function (Order $order) {
    $order = UpdateOrderStateToPacked::make()->action($order, false);
    $order->refresh();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toEqual(OrderStateEnum::PACKED);

    return $order;
})->depends('update order state to Handling');

test('update order state to Finalised ', function (Order $order) {
    $order = FinaliseOrder::make()->action($order);
    $order->refresh();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toEqual(OrderStateEnum::FINALISED);

    return $order;
})->depends('update order state to Handling');

test('create customer client', function () {
    $shop     = StoreShop::make()->action($this->organisation, Shop::factory()->definition());
    $customer = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

    $customerSalesChannel = StoreCustomerSalesChannel::make()->action($customer, $platform, [
        'reference' => 'test_manual_reference'
    ]);

    StoreCustomerSalesChannel::make()->action($customer, $platform, []);
    $customerClient = StoreCustomerClient::make()->action(
        $customerSalesChannel,
        array_merge(
            CustomerClient::factory()->definition(),
        )
    );
    $this->assertModelExists($customerClient);
    expect($customerClient->shop->code)->toBe($shop->code)
        ->and($customerClient->customer->reference)->toBe($customer->reference);

    return $customerClient;
});

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
})->depends('create customer client');

test('create invoice from customer', function () {
    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    $invoice = StoreInvoice::make()->action($this->customer, $invoiceData);
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->customer)->toBeInstanceOf(Customer::class)
        ->and($invoice->customer->stats->number_invoices)->toBe(2);

    return $invoice;
})->depends();

test('update invoice from customer', function ($invoice) {
    $invoice = UpdateInvoice::make()->action($invoice, [
        'reference' => '00001a'

    ]);
    expect($invoice->reference)->toBe('00001a');
})->depends('create invoice from customer');

test('create invoice from order', function (Order $order) {
    $transaction = $order->transactions->first();
    $invoiceData = Invoice::factory()->definition();
    data_set($invoiceData, 'billing_address', new Address(Address::factory()->definition()));
    data_set($invoiceData, 'reference', '00002');
    $invoice            = StoreInvoice::make()->action($order, $invoiceData);
    $invoiceTransaction = StoreInvoiceTransaction::make()->action($invoice, $transaction, [
        'date'            => now(),
        'tax_category_id' => $transaction->tax_category_id,
        'quantity'        => 10,
        'gross_amount'    => 1000,
        'net_amount'      => 1000,
    ]);
    $customer           = $invoice->customer;
    $this->shop->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($customer)->toBeInstanceOf(Customer::class)
        ->and($invoice->customer->id)->toBe($order->customer_id)
        ->and($invoice->reference)->toBe('00002')
        ->and($customer->stats->number_invoices)->toBe(3)
        ->and($this->shop->orderingStats->number_invoices)->toBe(3)
        ->and($invoiceTransaction)->toBeInstanceOf(InvoiceTransaction::class);


    return $invoice;
})->depends('create order', 'update invoice from customer');

test('update invoice transaction', function (Invoice $invoice) {
    $transaction        = $invoice->invoiceTransactions->first();
    $updatedTransaction = UpdateInvoiceTransaction::make()->action($transaction, [
        'quantity' => 100
    ]);
    expect($updatedTransaction)->toBeInstanceOf(InvoiceTransaction::class)
        ->and(intval($updatedTransaction->quantity))->toBe(100);

    return $updatedTransaction;
})->depends('create invoice from order');

test('delete invoice transaction', function (InvoiceTransaction $invoiceTransaction) {
    $invoice = $invoiceTransaction->invoice;
    DeleteInProcessInvoiceTransaction::make()->action($invoiceTransaction);
    $invoice->refresh();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->stats->number_invoice_transactions)->toBe(0);

    return $invoice;
})->depends('update invoice transaction');

test('create old order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action(parent: $this->customer, modelData: $modelData);


    $transactionData = Transaction::factory()->definition();
    $historicAsset   = $this->product->historicAsset;
    expect($historicAsset)->toBeInstanceOf(HistoricAsset::class);
    $transaction = StoreTransaction::make()->action($order, $historicAsset, $transactionData);

    $order->refresh();

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->state)->toBe(OrderStateEnum::CREATING)
        ->and($order->stats->number_item_transactions)->toBe(1)
        ->and($order->stats->number_item_transactions_at_submission)->toBe(1)
        ->and($transaction)->toBeInstanceOf(Transaction::class);

    $this->customer->refresh();
    $shop = $order->shop;
    $shop->refresh();
    $order->update([
        'updated_at' => Date::now()->subDays(40)->toDateString()
    ]);

    return $order;
});

test('create purge', function (Order $order) {
    $shop  = $order->shop;
    $purge = StorePurge::make()->action($shop, [
        'type'          => PurgeTypeEnum::MANUAL,
        'scheduled_at'  => now(),
        'inactive_days' => 30,
    ]);

    expect($purge)->toBeInstanceOf(Purge::class)
        ->and($purge->type)->toBe(PurgeTypeEnum::MANUAL)
        ->and($purge->stats->estimated_number_orders)->toBe(1);

    return $purge;
})->depends('create old order');

test('update purge', function (Purge $purge) {
    $newSchedule = Date::now()->addDays(5);
    $purge       = UpdatePurge::make()->action($purge, [
        'scheduled_at' => $newSchedule
    ]);

    expect($purge)->toBeInstanceOf(Purge::class)
        ->and(Carbon::parse($purge->scheduled_at)->toDateString())->toBe($newSchedule->toDateString());

    return $purge;
})->depends('create purge');

test('update purge order', function (Purge $purge) {
    $purgedOrder        = $purge->purgedOrders->first();
    $updatedPurgedOrder = UpdatePurgedOrder::make()->action($purgedOrder, [
        'error_message' => 'error test'
    ]);

    expect($updatedPurgedOrder)->toBeInstanceOf(PurgedOrder::class)
        ->and($updatedPurgedOrder->error_message)->toBe('error test');

    return $updatedPurgedOrder;
})->depends('create purge');

test('delete transaction', function (Order $order) {
    $transaction = $order->transactions->first();

    DeleteTransaction::make()->action($transaction);
    $order->refresh();

    expect($order->transactions()->count())->toBe(0);

    return $order;
})->depends('create old order');

test('UI create asset shipping', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.create', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'New schema')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New schema')
                    ->etc()
            )
            ->has('formData');
    });
});

test('UI show asset shipping', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.show', [$this->organisation->slug, $this->shop, $shippingZoneSchema]));
    $response->assertInertia(function (AssertableInertia $page) use ($shippingZoneSchema) {
        $page
            ->component('Org/Catalogue/ShippingZoneSchema')
            ->where('title', 'Shipping Zone Schema')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $shippingZoneSchema->name)
                    ->etc()
            )
            ->has('navigation')
            ->has('tabs');
    });
});

test('UI edit asset shipping', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.billables.shipping.edit', [$this->organisation->slug, $this->shop, $shippingZoneSchema]));
    $response->assertInertia(function (AssertableInertia $page) use ($shippingZoneSchema) {
        $page
            ->component('EditModel')
            ->where('title', 'Shipping Zone Schema')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $shippingZoneSchema->name)
                    ->etc()
            )
            ->has('navigation')
            ->has('formData');
    });
});

test('UI show ordering backlog', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.backlog', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Ordering/OrdersBacklog')
            ->where('title', 'Orders backlog')
            ->has('breadcrumbs', 4)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Orders backlog')
                    ->etc()
            );
    });
});

test('UI index ordering purges', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.index', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Ordering/Purges')
            ->where('title', 'Purges')
            ->has('breadcrumbs', 3)
            ->has('data')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'Purges')
                    ->etc()
            );
    });
});

test('UI create ordering purge', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.create', [$this->organisation->slug, $this->shop]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->where('title', 'New purge')
            ->has('breadcrumbs', 4)
            ->has('formData', fn ($page) => $page
                ->where('route', [
                    'name'       => 'grp.models.purge.store',
                    'parameters' => [
                        'shop' => $this->shop->id,
                    ]
                ])
                ->etc())
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', 'New purge')
                    ->etc()
            );
    });
});

test('UI edit ordering purge', function () {
    $purge = Purge::first();
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.ordering.purges.edit', [$this->organisation->slug, $this->shop, $purge]));
    $response->assertInertia(function (AssertableInertia $page) use ($purge) {
        $page
            ->component('EditModel')
            ->where('title', 'Purge')
            ->has('breadcrumbs', 3)
            ->has('formData', fn ($page) => $page
                ->where('args', [
                    'updateRoute' => [
                        'name'       => 'grp.models.purge.update',
                        'parameters' => $purge->id

                    ],
                ])
                ->etc())
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                    ->where('title', $purge->scheduled_at->toISOString())
                    ->etc()
            );
    });
});

test('UI get section route index', function () {
    $sectionScope = GetSectionRoute::make()->handle('grp.org.shops.show.ordering.orders.index', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug
    ]);
    expect($sectionScope)->toBeInstanceOf(AikuScopedSection::class)
        ->and($sectionScope->organisation_id)->toBe($this->organisation->id)
        ->and($sectionScope->code)->toBe(AikuSectionEnum::SHOP_ORDERING->value)
        ->and($sectionScope->model_slug)->toBe($this->shop->slug);
});

test('orders search', function () {
    $this->artisan('search:orders')->assertExitCode(0);

    $order = Order::first();
    ReindexOrdersSearch::run($order);
    expect($order->universalSearch()->count())->toBe(1);
});

test('invoices search', function () {
    $this->artisan('search:invoices')->assertExitCode(0);

    $invoice = Invoice::first();
    ReindexInvoiceSearch::run($invoice);
    expect($invoice->universalSearch()->count())->toBe(1);
});

test('delivery notes search', function () {
    $this->artisan('search:delivery_notes')->assertExitCode(0);

    $deliveryNote = DeliveryNote::first();
    ReindexDeliveryNotesSearch::run($deliveryNote);
    expect($deliveryNote->universalSearch()->count())->toBe(1);
});

test('test reset intervals', function () {
    $this->artisan('intervals:reset-day')->assertExitCode(0);
    $this->artisan('intervals:reset-week')->assertExitCode(0);
    $this->artisan('intervals:reset-month')->assertExitCode(0);
    $this->artisan('intervals:reset-quarter')->assertExitCode(0);
    $this->artisan('intervals:reset-year')->assertExitCode(0);
});

test('purge hydrators', function () {
    $purge = Purge::first();
    HydratePurges::run($purge);
    $this->artisan('hydrate:purges')->assertExitCode(0);
});

test('order hydrators', function () {
    $order = Order::first();
    HydrateOrders::run($order);
    $this->artisan('hydrate:orders ')->assertExitCode(0);
});

test('Ordering hydrators', function () {
    $this->artisan('hydrate', [
        '--sections' => 'ordering',
    ])->assertExitCode(0);
});

test('Pay order creates payment and attaches to order', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Cash Account',
        ]
    );

    $amount    = 50.25;
    $reference = 'PAY-'.uniqid();

    $payment = PayOrder::make()->action($order, $paymentAccount, [
        'amount'    => $amount,
        'reference' => $reference,
        'status'    => PaymentStatusEnum::SUCCESS,
        'state'     => PaymentStateEnum::COMPLETED,
    ]);

    $order->refresh();

    expect($payment->amount)->toBe((string) $amount)
        ->and($payment->reference)->toBe($reference)
        ->and($order->payments()->where('payments.id', $payment->id)->exists())->toBeTrue();
});

test('Pay order with accounts payment account creates credit transaction', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Accounts Account',
        ]
    );

    // Ensure this account behaves as an accounts ledger so PayOrder creates a credit transaction
    $paymentAccount->is_accounts = true;
    $paymentAccount->save();

    $amount    = 75.00;
    $payment   = PayOrder::make()->action($order, $paymentAccount, [
        'amount' => $amount,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $payment->refresh();

    expect($payment->creditTransaction)->not->toBeNull()
        ->and((float) $payment->creditTransaction->amount)->toBe((float) (-$amount))
        ->and($payment->creditTransaction->payment_id)->toBe($payment->id);
});

test('Pay order attaches payment to invoice when invoice exists', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $orderData = Order::factory()->definition();
    data_set($orderData, 'billing_address', $billingAddress);
    data_set($orderData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $orderData);

    $invoice = StoreInvoice::make()->action($order, [
        'type' => InvoiceTypeEnum::INVOICE,
        'currency_id' => $this->shop->currency_id,
        'net_amount' => 0,
        'total_amount' => 0,
        'gross_amount' => 0,
        'tax_amount' => 0,
    ]);

    $paymentAccount = StoreOrgPaymentServiceProviderAccount::make()->action(
        $this->organisation,
        PaymentServiceProvider::where('type', PaymentServiceProviderTypeEnum::CASH->value)->first(),
        [
            'code' => 'ACC'.mt_rand(1000, 9999),
            'name' => 'Cash Account 2',
        ]
    );

    $payment = PayOrder::make()->action($order, $paymentAccount, [
        'amount' => 10.00,
        'status' => PaymentStatusEnum::SUCCESS,
        'state'  => PaymentStateEnum::COMPLETED,
    ]);

    $payment->refresh();
    $invoice->refresh();

    expect($payment->invoices()->where('invoices.id', $invoice->id)->exists())->toBeTrue();
});

test('create shipping zone schema', function () {
    $shippingZoneSchema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    expect($shippingZoneSchema)->toBeInstanceOf(ShippingZoneSchema::class);

    return $shippingZoneSchema;
});

test('update shipping zone schema', function ($shippingZoneSchema) {
    $shippingZoneSchema = UpdateShippingZoneSchema::make()->action($shippingZoneSchema, ShippingZoneSchema::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);
})->depends('create shipping zone schema');

test('create shipping zone', function ($shippingZoneSchema) {
    $shippingZone = StoreShippingZone::make()->action($shippingZoneSchema, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZoneSchema);

    return $shippingZone;
})->depends('create shipping zone schema');

test('update shipping zone', function ($shippingZone) {
    $shippingZone = UpdateShippingZone::make()->action($shippingZone, ShippingZone::factory()->definition());
    $this->assertModelExists($shippingZone);
})->depends('create shipping zone');


test('shipping zone schemas hydrators', function () {
    $shippingZoneSchema = ShippingZoneSchema::first();
    HydrateShippingZoneSchemas::run($shippingZoneSchema);
    $this->artisan('hydrate:shipping_zone_schemas')->assertExitCode(0);
});

test('shipping zone hydrators', function () {
    $shippingZone = ShippingZone::first();
    HydrateShippingZones::run($shippingZone);
    $this->artisan('hydrate:shipping_zones')->assertExitCode(0);
});

test('order is_shipping_tbc becomes true when shipping zone price type is TBC', function () {
    // Create an order
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    // Create a shipping zone schema and a zone with nested orders.price.type
    $schema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());

    $zone = StoreShippingZone::make()->action($schema, array_merge(
        ShippingZone::factory()->definition(),
        [
            'price' => [
                'orders' => [
                    'price' => [
                        'type' => 'TBC',
                    ],
                ],
            ],
        ]
    ));

    // Attach zone to order
    $order->update(['shipping_zone_id' => $zone->id]);

    // Run action
    OrderUpdateIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeTrue();
});

test('order is_shipping_tbc becomes false when shipping zone price type is not TBC', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, ShippingZoneSchema::factory()->definition());
    $zone   = StoreShippingZone::make()->action($schema, array_merge(
        ShippingZone::factory()->definition(),
        [
            'price' => [
                'orders' => [
                    'price' => [
                        'type' => 'FIXED',
                    ],
                ],
            ],
        ]
    ));

    $order->update(['shipping_zone_id' => $zone->id, 'is_shipping_tbc' => true]);

    OrderUpdateIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});

test('command updates is_shipping_tbc to true for TBC zone', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'Schema A',
    ]);
    $zone = StoreShippingZone::make()->action($schema, [
        'code'        => 'TBC-ZONE',
        'name'        => 'TBC Zone',
        'status'      => true,
        'price'       => ['type' => 'TBC'],
        'territories' => [],
        'position'    => 1,
    ]);

    $order->update([
        'shipping_zone_id' => $zone->id,
        'shipping_engine'  => \App\Enums\Ordering\Order\OrderShippingEngineEnum::AUTO,
        'is_shipping_tbc'  => false,
    ]);

    \Artisan::call('order:is-shipping-tbc', ['--slug' => $order->slug]);

    expect($order->fresh()->is_shipping_tbc)->toBeTrue();
});

test('command updates is_shipping_tbc to false for non-TBC zone', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    $schema = StoreShippingZoneSchema::make()->action($this->shop, [
        'name' => 'Schema B',
    ]);
    $zone = StoreShippingZone::make()->action($schema, [
        'code'        => 'FLAT-ZONE',
        'name'        => 'Flat Zone',
        'status'      => true,
        'price'       => ['type' => 'Flat'],
        'territories' => [],
        'position'    => 1,
    ]);

    $order->update([
        'shipping_zone_id' => $zone->id,
        'shipping_engine'  => \App\Enums\Ordering\Order\OrderShippingEngineEnum::AUTO,
        'is_shipping_tbc'  => true,
    ]);

    \Artisan::call('order:is-shipping-tbc', ['--slug' => $order->slug]);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});

test('order is_shipping_tbc false when no shipping zone present', function () {
    $order = StoreOrder::make()->action($this->customer, Order::factory()->definition());

    // Ensure no shipping zone is linked
    $order->update(['shipping_zone_id' => null, 'is_shipping_tbc' => true]);

    OrderUpdateIsShippingTBC::run($order);

    expect($order->fresh()->is_shipping_tbc)->toBeFalse();
});
