<?php

use App\Actions\Procurement\ShoppingListItem\SuggestSupplierShoppingList;

function moqCandidate(array $overrides = []): array
{
    return array_merge([
        'org_supplier_product_id' => 1,
        'code'                    => 'ABC',
        'name'                    => 'A thing',
        'cost'                    => 2.0,
        'units_per_carton'        => 10,
        'minimum_carton_order'    => 5,
        'never_stocked'           => false,
        'health_rank'             => 'B',
        'cap_exempt'              => false,
        'our_stock'               => 0.0,
        'days_of_cover'           => 0.0,
        'recommended'             => 12.0,
        'quarterly_usage'         => 40.0,
    ], $overrides);
}

function moqFill(array $candidates, float $budget): array
{
    $action = new SuggestSupplierShoppingList();

    return (fn () => $this->greedyFill($candidates, $budget))->call($action);
}

it('raises a small recommendation up to the supplier minimum order', function () {
    $lines = moqFill([moqCandidate()], 10000);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['quantity'])->toBe(50.0)
        ->and($lines[0]['cartons'])->toBe(5.0);
});

it('keeps a recommendation that already clears the minimum', function () {
    $lines = moqFill([moqCandidate(['recommended' => 84.0])], 10000);

    expect($lines[0]['quantity'])->toBe(90.0);
});

it('drops a line the remaining budget cannot buy at the minimum', function () {
    $lines = moqFill([moqCandidate()], 60);

    expect($lines)->toBeEmpty();
});

it('treats a missing minimum as one carton', function () {
    $lines = moqFill([moqCandidate(['minimum_carton_order' => null])], 10000);

    expect($lines[0]['quantity'])->toBe(20.0);
});
