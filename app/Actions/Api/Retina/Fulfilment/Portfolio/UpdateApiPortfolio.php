<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Portfolio;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\PortfolioResource;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class UpdateApiPortfolio extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Portfolio $portfolio, array $modelData): Portfolio
    {
        $portfolio = UpdatePortfolio::make()->action($portfolio, $modelData);

        return $portfolio;
    }

    public function rules(): array
    {
        $rules = [
            'customer_price' => ['sometimes', 'numeric', 'min:0'],
            'customer_product_name' => ['sometimes', 'string'],
            'customer_description' => ['sometimes', 'string'],
        ];
        return $rules;
    }
    /**
     * @throws \Throwable
     */
    public function asController(Portfolio $portfolio, ActionRequest $request): Portfolio
    {
        $this->initialisationFromFulfilment($request);

        return $this->handle($portfolio, $this->validatedData);
    }

    public function jsonResponse(Portfolio $portfolio): PortfolioResource
    {
        return PortfolioResource::make($portfolio);
    }

}
