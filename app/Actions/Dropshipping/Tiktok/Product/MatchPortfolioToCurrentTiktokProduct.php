<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MatchPortfolioToCurrentTiktokProduct extends RetinaAction
{
    use AsAction;

    public function handle(Portfolio $portfolio, array $modelData): void
    {
        $tiktokUserId = Arr::get($modelData, 'platform_product_id');

        $portfolio->update([
            'platform_product_id' => $tiktokUserId,
            'platform_product_variant_id' => $tiktokUserId
        ]);

        $portfolio->refresh();

        CheckTiktokPortfolio::run($portfolio);
    }

    public function rules(): array
    {
        return [
            'platform_product_id' => ['required', 'string'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($portfolio, $this->validatedData);
    }
}
