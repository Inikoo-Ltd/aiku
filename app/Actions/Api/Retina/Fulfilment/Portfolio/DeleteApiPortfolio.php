<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-13h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Portfolio;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaApiAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Ordering\Order;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteApiPortfolio extends RetinaApiAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Portfolio $portfolio): JsonResponse
    {
        $orders = Order::where('customer_sales_channel_id', $this->customerSalesChannel->id)
            ->with('transactions')
            ->get();

        $usedInOrders = $orders->contains(function ($order) use ($portfolio) {
            return $order->transactions->contains(function ($trn) use ($portfolio) {
                return $trn->model_type === 'Product' &&
                    $trn->model_id === $portfolio->item->id;
            });
        });

        if ($usedInOrders) {
            $portfolio = UpdatePortfolio::make()->action($portfolio, ['status' => false]);
            $portfolio->refresh();
            return response()->json([
                'message' => 'Portfolio cannot be deleted because it exists in one or more orders. It has been disabled instead.',
                'portfolio_id' => $portfolio->id,
                'status' => $portfolio->status
            ]);
        } else {
            DeletePortfolio::make()->action($this->customerSalesChannel, $portfolio, []);
            return response()->json([
                'message' => 'Portfolio has been deleted.',
                'portfolio_id' => $portfolio->id
            ]);
        }
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromFulfilment($request);

        return $this->handle($portfolio);
    }
}
