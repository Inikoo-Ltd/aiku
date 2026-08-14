<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\Product\MatchBulkNewProductToCurrentEbay;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class MatchRetinaBulkNewProductToCurrentEbay extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Exception
     */
    /**
     * @return array{matched: int, ignored: int}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): array
    {
        return MatchBulkNewProductToCurrentEbay::run($customerSalesChannel, $attributes);
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['sometimes', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
