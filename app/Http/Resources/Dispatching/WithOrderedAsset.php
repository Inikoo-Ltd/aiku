<?php

namespace App\Http\Resources\Dispatching;

trait WithOrderedAsset
{
    /**
     * @return array{code: string, name: string, quantity: float}|null
     */
    protected function getOrderedAssetForFractionalQuantity(): ?array
    {
        if (!$this->ordered_asset || floor((float) $this->quantity_required) == (float) $this->quantity_required) {
            return null;
        }

        $orderedAsset = json_decode($this->ordered_asset, true);
        $orderedAsset['quantity'] = (float) $orderedAsset['quantity'];

        return $orderedAsset;
    }

    /**
     * @return array{transaction_id: int, sets_ordered: float, product: array{code: string, name: string}, parts: array<int, array{code: string, name: string, quantity: float}>, route: null}|null
     */
    protected function getOrderedAssetIndivisibleSet(): ?array
    {
        if (!$this->indivisible_set) {
            return null;
        }

        $set = json_decode($this->indivisible_set, true);

        return [
            'transaction_id' => $this->transaction_id,
            'sets_ordered'   => (float) $set['sets_ordered'],
            'product'        => [
                'code' => $set['product_code'],
                'name' => $set['product_name'],
            ],
            'parts'          => collect($set['parts'] ?? [])->map(fn (array $part) => [
                'code'     => $part['code'],
                'name'     => $part['name'],
                'quantity' => (float) $part['quantity'],
            ])->sortBy('code')->values()->all(),
            'route'          => null,
        ];
    }
}
