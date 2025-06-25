<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-10h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaApiAction;
use App\Http\Resources\Api\PortfolioResource;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class ShowApiPortfolio extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Portfolio $portfolio): Portfolio
    {
        return $portfolio;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): Portfolio
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($portfolio);
    }

    public function jsonResponse(Portfolio $portfolio): PortfolioResource
    {
        return PortfolioResource::make($portfolio);
    }

}
