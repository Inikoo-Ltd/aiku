<?php

use App\Http\Resources\Dispatching\DeliveryNoteItemsStateUnassignedResource;

function orderedAssetFor(string $quantityRequired): ?array
{
    $row = (object) [
        'quantity_required' => $quantityRequired,
        'ordered_asset'     => json_encode(['code' => 'FOKG-224-10ml', 'name' => 'Lavender Fragrance Oil', 'quantity' => '5.000']),
    ];

    return (fn () => $this->getOrderedAssetForFractionalQuantity())->call(new DeliveryNoteItemsStateUnassignedResource($row));
}

test('fractional stock quantity shows the ordered asset', function () {
    expect(orderedAssetFor('0.050000'))->toBe(['code' => 'FOKG-224-10ml', 'name' => 'Lavender Fragrance Oil', 'quantity' => 5.0]);
});

test('whole stock quantity hides the ordered asset', function () {
    expect(orderedAssetFor('3.000000'))->toBeNull();
});
