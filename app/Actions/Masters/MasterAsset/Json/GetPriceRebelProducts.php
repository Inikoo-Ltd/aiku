<?php

/*
 * Author Louis Perez
 * Created on 22-07-2026-15h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class GetPriceRebelProducts extends GrpAction
{
    public function handle(MasterAsset $masterAsset, array $modelData): array
    {
        $getPrice = data_get($modelData, 'type', 'price') == 'price';

        return $masterAsset
            ->products()
            ->with(['family', 'shop', 'currency'])
            ->get()
            ->mapWithKeys(function ($product) use ($getPrice) {
                $shop = $product->shop;
                if (
                    !data_get($shop->settings, 'catalog.follow_master_pricing', true) ||
                    $product->family->not_follow_master_prices ||
                    $product->not_follow_master_prices
                ) {
                    return [
                        $shop->id => [
                            'id'                => $product->id,
                            'shop_id'           => $shop->id,
                            'shop_code'         => $shop->code,
                            'currency_code'     => $product->currency?->code ?? $shop->currency->code,
                            'value'             => $getPrice ? $product->price : $product->rrp,
                        ]
                    ];
                }
                
                return [];

            })
            ->toArray();
    }

    public function jsonResponse(array $rebelsData): array
    {
        return $rebelsData;
    }

    public function rules()
    {
        return [
            'type'  => ['required', 'string']
        ];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): array
    {
        $this->initialisation(group(), $request);

        return $this->handle($masterAsset, $this->validatedData);
    }
}
