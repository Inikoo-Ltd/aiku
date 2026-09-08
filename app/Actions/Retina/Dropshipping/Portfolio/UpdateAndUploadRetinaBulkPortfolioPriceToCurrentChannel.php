<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAndUploadRetinaBulkPortfolioPriceToCurrentChannel extends RetinaAction
{
    use AsAction;

    public function handle(array $modelData, $isDraft = false): void
    {
        $items = Arr::pull($modelData, 'items');

        foreach ($items as $itemId) {
            $portfolio = Portfolio::find($itemId);

            if (! $portfolio) {
                continue;
            }

            try {
                UpdateAndUploadRetinaPortfolioToCurrentChannel::run($portfolio, $modelData, $isDraft);
            } catch (ValidationException) {
                continue;
            }
        }
    }

    public function rules(): array
    {
        return [
            'items' => ['array'],
            'pricing_type' => ['required', Rule::in(['percent', 'fixed', 'not_follow'])],
            'pricing_value' => ['exclude_if:pricing_type,not_follow', 'required', 'numeric', 'gte:-100'],
        ];
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {

        $this->initialisation($request);
        $this->handle($this->validatedData);
    }

    public function asDraft(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($this->validatedData, true);
    }

}
