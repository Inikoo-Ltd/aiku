<?php

/*
 * author Arya Permana - Kirin
 * created on 10-07-2025-10h-29m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Actions\Dropshipping\Shopify\Product\AdoptShopifyProductVariant;
use App\Actions\Dropshipping\Shopify\Product\GetShopifyListedProducts;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class GetShopifyProducts extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array|null
    {
        $products = GetShopifyListedProducts::run(
            $customerSalesChannel->user,
            (string) Arr::get($modelData, 'query', ''),
            (int) Arr::get($modelData, 'offset', 0),
            (int) Arr::get($modelData, 'limit', 50)
        );

        $portfolio = Arr::get($modelData, 'portfolio') ? $customerSalesChannel->portfolios()->find(Arr::get($modelData, 'portfolio')) : null;

        if ($portfolio && AdoptShopifyProductVariant::isEnabledFor($customerSalesChannel)) {
            $products = array_map(fn (array $product) => [...$product, 'variant_to_link' => $this->variantToLink($portfolio, $product)], $products);
        }

        return [
            'products' => $products
        ];
    }

    private function variantToLink(Portfolio $portfolio, array $product): ?string
    {
        $numberVariants = Arr::get($product, 'number_variants', 0);

        if ($numberVariants < 2 || $numberVariants >= GetShopifyListedProducts::VARIANTS_READ_PER_PRODUCT) {
            return null;
        }

        $matchingVariants = AdoptShopifyProductVariant::variantsCarryingPortfolioSku(
            $portfolio,
            array_map(fn (string $sku) => ['id' => '', 'sku' => $sku], Arr::get($product, 'sku_list', []))
        );

        return count($matchingVariants) === 1 ? $matchingVariants[0]['sku'] : null;
    }

    public function rules(): array
    {
        return [
            'query'     => ['nullable', 'string'],
            'portfolio' => ['nullable', 'integer'],
            'offset' => ['nullable', 'numeric'],
            'limit'  => ['nullable', 'numeric', 'min:1', 'max:100']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge([
            'query'     => $request->input('query'),
            'portfolio' => $request->input('portfolio'),
            'offset' => $request->input('offset'),
            'limit'  => $request->input('limit'),
        ]);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request)
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
