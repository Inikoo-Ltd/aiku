<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Jul 2025 11:35:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayOffer;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\Dropshipping\Shopify\Product\UpdateShopifyProductVariant;
use App\Actions\Dropshipping\WooCommerce\Product\UpdateWooProduct;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Portfolio;
use App\Traits\SanitizeInputs;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAndUploadRetinaPortfolioToCurrentChannel extends RetinaAction
{
    use AsAction;
    use SanitizeInputs;

    public function handle(Portfolio $portfolio, array $modelData, $isDraft = false): void
    {
        $portfolio = UpdatePortfolio::run($portfolio, $modelData);

        if (! $isDraft) {
            match ($portfolio->platform->type) {
                PlatformTypeEnum::EBAY => UpdateEbayOffer::run($portfolio),
                PlatformTypeEnum::WOOCOMMERCE => UpdateWooProduct::run($portfolio),
                PlatformTypeEnum::SHOPIFY => UpdateShopifyProductVariant::run($portfolio),
                default => null
            };
        }
    }

    public function rules(): array
    {
        return [
            'customer_product_name' => ['sometimes', 'string'],
            'customer_price' => ['sometimes', 'string', 'numeric'],
            'customer_description' => ['sometimes', 'string'],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->setSanitizeFields([
            'title',
            'price',
        ]);
        $this->sanitizeInputs();
        $this->sanitizeHtml('description');

        $this->set('customer_product_name', $request->input('title'));
        $this->set('customer_price', (string) $request->input('price'));
        $this->set('customer_description', $request->input('description'));
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData);
    }

    public function asDraft(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->enableSanitize();
        $this->initialisation($request);
        $this->handle($portfolio, $this->validatedData, true);
    }

    public string $commandSignature = 'UpdateAndUploadRetinaPortfolioToCurrentChannel {portfolio_id}';

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        $this->handle($portfolio, [
            'customer_price' => '999'
        ]);
    }
}
