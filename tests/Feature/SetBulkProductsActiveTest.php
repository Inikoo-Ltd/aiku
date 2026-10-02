<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Bali Office, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    [, $seedProduct] = createProduct($this->shop);
    $this->family    = $seedProduct->family;

    $this->makeProductInState = function (ProductStateEnum $state): Product {
        $product = StoreProduct::make()->action(
            $this->family,
            array_merge(Product::factory()->definition(), [
                'code'  => 'SBA'.substr(uniqid(), -8),
                'price' => 10,
                'unit'  => 'piece',
            ])
        );
        $product->updateQuietly(['state' => $state]);

        return $product;
    };
});

test('selected products in process are set as active', function () {
    $firstInProcess  = ($this->makeProductInState)(ProductStateEnum::IN_PROCESS);
    $secondInProcess = ($this->makeProductInState)(ProductStateEnum::IN_PROCESS);
    $notSelected     = ($this->makeProductInState)(ProductStateEnum::IN_PROCESS);

    $this->patch(route('grp.models.product.bulk_set_active', ['shop' => $this->shop->id]), [
        'products' => [$firstInProcess->id, $secondInProcess->id],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($firstInProcess->refresh()->state)->toBe(ProductStateEnum::ACTIVE)
        ->and($secondInProcess->refresh()->state)->toBe(ProductStateEnum::ACTIVE)
        ->and($notSelected->refresh()->state)->toBe(ProductStateEnum::IN_PROCESS);
});

test('discontinued products are not brought back by the bulk set active', function () {
    $discontinued = ($this->makeProductInState)(ProductStateEnum::DISCONTINUED);

    $this->patch(route('grp.models.product.bulk_set_active', ['shop' => $this->shop->id]), [
        'products' => [$discontinued->id],
    ])->assertSessionHasNoErrors();

    expect($discontinued->refresh()->state)->toBe(ProductStateEnum::DISCONTINUED);
});

test('products from another shop are not touched', function () {
    $inProcess = ($this->makeProductInState)(ProductStateEnum::IN_PROCESS);
    [, , $otherShop] = createOwnShop('set-bulk-products-active-other-shop');

    $this->patch(route('grp.models.product.bulk_set_active', ['shop' => $otherShop->id]), [
        'products' => [$inProcess->id],
    ])->assertSessionHasNoErrors();

    expect($inProcess->refresh()->state)->toBe(ProductStateEnum::IN_PROCESS);
});

test('an empty selection is rejected', function () {
    $this->patch(route('grp.models.product.bulk_set_active', ['shop' => $this->shop->id]), [
        'products' => [],
    ])->assertSessionHasErrors('products');
});

test('the in process products page offers the bulk set active route', function () {
    get(route('grp.org.shops.show.catalogue.products.in_process_products.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('bulk_set_active_route.name', 'grp.models.product.bulk_set_active')
            ->where('bulk_set_active_route.parameters.shop', $this->shop->id)
    );
});

test('the current products page does not offer the bulk set active route', function () {
    get(route('grp.org.shops.show.catalogue.products.current_products.index', [
        $this->organisation->slug,
        $this->shop->slug,
    ]))->assertOk()->assertInertia(
        fn (AssertableInertia $page) => $page->where('bulk_set_active_route', null)
    );
});
