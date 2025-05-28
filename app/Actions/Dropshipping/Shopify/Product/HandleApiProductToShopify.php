<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\Product;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;

class HandleApiProductToShopify extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(ShopifyUser $shopifyUser, array $attributes): void
    {
        $portfolios = $shopifyUser
            ->customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->whereIn('id', Arr::get($attributes, 'portfolios'))
            ->get();

        foreach ($portfolios as $portfolio) {
            try {
                $images = [];
                foreach ($portfolio->item->images as $image) {
                    $images[] = [
                        "attachment" => $image->getBase64Image()
                    ];
                }
            } catch (\Exception $e) {
                Sentry::captureException($e);
            }

            $body = [
                "product" => [
                    "id" => $portfolio->item->id,
                    "title" => $portfolio->item->name,
                    "body_html" => $portfolio->item->description,
                    "vendor" => $portfolio->item->shop->name,
                    "product_type" => $portfolio->item->family?->name,
                    "images" => $images
                ]
            ];

            Log::info('before-dispatch-' .$portfolio->id);
            RequestApiUploadProductToShopify::dispatch($shopifyUser, $portfolio, $body);
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer', Rule::exists('portfolios', 'id')],
        ];
    }

    public function asController(ShopifyUser $shopifyUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($shopifyUser, $this->validatedData);
    }
}
