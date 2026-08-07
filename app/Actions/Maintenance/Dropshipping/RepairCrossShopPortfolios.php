<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Ordering\Transaction;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairCrossShopPortfolios
{
    use AsAction;

    /**
     * @throws \Throwable
     */
    public function handle(bool $apply, ?Command $command = null): array
    {
        $summary = [
            'portfolios_fixed'      => 0,
            'portfolios_unmatched'  => 0,
            'transactions_fixed'    => 0,
            'transactions_unmatched' => 0,
        ];

        foreach ($this->crossShopPortfolios() as $portfolio) {
            $product = $this->counterpart($portfolio->shop_id, $portfolio->item->code);

            if (!$product) {
                $summary['portfolios_unmatched']++;
                $command?->warn("portfolio $portfolio->id: no $portfolio->item_code in shop $portfolio->shop_id");
                continue;
            }

            if ($apply) {
                $portfolio->update([
                    'item_id'   => $product->id,
                    'item_code' => $product->code,
                    'item_name' => $product->name,
                    'barcode'   => $product->barcode,
                    'sku'       => StorePortfolio::make()->getSKU($product),
                ]);
            }
            $summary['portfolios_fixed']++;
        }

        foreach ($this->crossShopOpenTransactions() as $transaction) {
            $product = $this->counterpart($transaction->shop_id, $transaction->model->code);

            if (!$product?->current_historic_asset_id) {
                $summary['transactions_unmatched']++;
                $command?->warn("transaction $transaction->id: no counterpart for order $transaction->order_id");
                continue;
            }

            if ($apply) {
                $order    = $transaction->order;
                $quantity = $transaction->quantity_ordered;
                DeleteTransaction::make()->action($transaction);
                StoreTransaction::make()->action(
                    order: $order,
                    historicAsset: $product->currentHistoricProduct,
                    modelData: ['quantity_ordered' => $quantity]
                );
            }
            $summary['transactions_fixed']++;
        }

        return $summary;
    }

    private function crossShopPortfolios()
    {
        return Portfolio::where('portfolios.item_type', 'Product')
            ->join('products', 'products.id', '=', 'portfolios.item_id')
            ->whereColumn('products.shop_id', '!=', 'portfolios.shop_id')
            ->select('portfolios.*')
            ->with('item')
            ->cursor();
    }

    private function crossShopOpenTransactions()
    {
        return Transaction::where('transactions.model_type', 'Product')
            ->join('products', 'products.id', '=', 'transactions.model_id')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->whereColumn('products.shop_id', '!=', 'transactions.shop_id')
            ->whereIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])
            ->select('transactions.*')
            ->with('model')
            ->cursor();
    }

    private function counterpart(int $shopId, ?string $code): ?Product
    {
        return Product::where('shop_id', $shopId)->where('code', $code)->first();
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_cross_shop_portfolios {--apply}';
    }

    public function getCommandDescription(): string
    {
        return 'Point portfolios and open order transactions at the product of their own shop';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $summary = $this->handle((bool)$command->option('apply'), $command);

        $command->table(array_keys($summary), [$summary]);
        if (!$command->option('apply')) {
            $command->info('dry run, use --apply to write');
        }

        return 0;
    }
}
