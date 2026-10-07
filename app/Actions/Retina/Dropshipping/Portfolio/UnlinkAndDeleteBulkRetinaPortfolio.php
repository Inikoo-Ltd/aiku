<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 30 Oct 2025 15:32:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UnlinkAndDeleteBulkRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    /**
     * Each product is also changed in the customer's store, about a second per product on Shopify,
     * so a large selection ran past the 45s request limit and stopped half way. Up to
     * SYNC_LIMIT products are removed while the customer waits; more go to the queue.
     */
    public const int SYNC_LIMIT = 20;

    /**
     * @return array{deleted: int, queued: int}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): array
    {
        $portfolios = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)
            ->whereIn('id', Arr::get($modelData, 'portfolios', []))
            ->with('customerSalesChannel.platform')
            ->get();

        if ($portfolios->count() > self::SYNC_LIMIT) {
            foreach ($portfolios as $portfolio) {
                DeletePortfolio::dispatch($portfolio);
            }

            return ['deleted' => 0, 'queued' => $portfolios->count()];
        }

        foreach ($portfolios as $portfolio) {
            DeleteRetinaPortfolio::run($portfolio);
        }

        return ['deleted' => $portfolios->count(), 'queued' => 0];
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer->id;
    }

    /**
     * @return array{deleted: int, queued: int}
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
