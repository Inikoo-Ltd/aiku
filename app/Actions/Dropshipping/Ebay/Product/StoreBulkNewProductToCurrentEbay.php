<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreBulkNewProductToCurrentEbay extends RetinaAction
{
    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser, array $attributes = []): void
    {
        $portfolios = $ebayUser
            ->customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            StoreNewProductToCurrentEbay::dispatch($ebayUser, $portfolio);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($ebayUser, $this->validatedData);
    }
}
