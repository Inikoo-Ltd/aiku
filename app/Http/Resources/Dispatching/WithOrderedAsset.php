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
}
