<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use App\Actions\RetinaAction;
use App\Enums\Catalogue\Portfolio\PortfolioPlatformAvailabilityOptionEnum;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class SyncronisePortfoliosToWooCommerce extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    private string $mode = PortfolioPlatformAvailabilityOptionEnum::BRAVE->value;

    /**
     * @throws \Exception
     */
    public function handle(WooCommerceUser $wooCommerceUser, array $attributes = [])
    {
        $portfolios = $wooCommerceUser
            ->customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            match ($this->mode) {
                PortfolioPlatformAvailabilityOptionEnum::USE_EXISTING->value => SyncExistingPortfolioWooCommerce::dispatch($wooCommerceUser, $portfolio),
                PortfolioPlatformAvailabilityOptionEnum::DUPLICATE->value => UploadPortfolioWooCommerce::dispatch($wooCommerceUser, $portfolio),
                default => UploadPortfolioWooCommerceBraveMode::dispatch($wooCommerceUser, $portfolio)
            };
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function asController(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        $this->mode = PortfolioPlatformAvailabilityOptionEnum::DUPLICATE->value;
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $this->validatedData);
    }

    public function asBatchSync(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        $this->mode = PortfolioPlatformAvailabilityOptionEnum::USE_EXISTING->value;
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $this->validatedData);
    }

    public function asBraveMode(WooCommerceUser $wooCommerceUser, ActionRequest $request)
    {
        $this->mode = PortfolioPlatformAvailabilityOptionEnum::BRAVE->value;
        $this->initialisation($request);

        $this->handle($wooCommerceUser, $this->validatedData);
    }
}
