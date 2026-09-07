<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\Dropshipping\Portfolio\MatchBulkPortfoliosToPlatform;
use App\Actions\OrgAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class MatchBulkNewProductToCurrentWooCommerce extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public string $jobQueue = 'dropshipping-long';

    /**
     * @return array{matched: int, ignored: int}
     *
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes = []): array
    {
        return MatchBulkPortfoliosToPlatform::run($customerSalesChannel, $attributes);
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['sometimes', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);

        MatchBulkPortfoliosToPlatform::dispatch($customerSalesChannel, $this->validatedData);
    }
}
