<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncSupplierProductsDiscontinuingFromSkos
{
    use AsAction;

    public int $restored = 0;

    public string $commandSignature = 'supply-chain:supplier-products-follow-discontinuing-skos
        {--dry-run : only count, per supplier, what would turn discontinuing and what would go back to active}';

    private const array RETIRING_STOCK_STATES = [
        StockStateEnum::DISCONTINUING->value,
        StockStateEnum::DISCONTINUED->value,
    ];

    /**
     * Active supplier products of the group whose SKOs are all discontinuing or discontinued, at least one still discontinuing (all
     * discontinued is DiscontinueDeadOrgSupplierProducts' work). Only the group row follows: the organisation rows keep their own
     * state, since a discontinuing one would stop goods-in from adding it and lines from being moved, so purchase orders still open
     * carry on as they are and hold nothing back. One a person put back to active is left alone.
     */
    public function toDiscontinuing(?int $stockId = null): Builder
    {
        return $this->query($stockId)
            ->where('sp.state', SupplierProductStateEnum::ACTIVE->value)
            ->whereRaw("sp.data->>'".UpdateSupplierProduct::KEPT_ACTIVE_BY_HAND_KEY."' is null")
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('stock_has_supplier_products as shs')
                ->join('stocks as st', 'st.id', 'shs.stock_id')
                ->whereColumn('shs.supplier_product_id', 'sp.id')
                ->where('st.state', StockStateEnum::DISCONTINUING->value))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('stock_has_supplier_products as shs')
                ->join('stocks as st', 'st.id', 'shs.stock_id')
                ->whereColumn('shs.supplier_product_id', 'sp.id')
                ->whereNotIn('st.state', self::RETIRING_STOCK_STATES));
    }

    /**
     * Supplier products this sync turned discontinuing that supply a SKO no longer discontinuing. A discontinuing state a buyer set
     * carries no mark, so it is never undone here.
     */
    public function toRestore(?int $stockId = null): Builder
    {
        return $this->query($stockId)
            ->where('sp.state', SupplierProductStateEnum::DISCONTINUING->value)
            ->whereRaw("sp.data->>'".UpdateSupplierProduct::FOLLOWS_DISCONTINUING_SKOS_KEY."' is not null")
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('stock_has_supplier_products as shs')
                ->join('stocks as st', 'st.id', 'shs.stock_id')
                ->whereColumn('shs.supplier_product_id', 'sp.id')
                ->whereNotIn('st.state', self::RETIRING_STOCK_STATES));
    }

    private function query(?int $stockId): Builder
    {
        return DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', 'sp.supplier_id')
            ->whereNull('sp.deleted_at')
            ->whereNull('s.deleted_at')
            ->when($stockId, fn (Builder $query) => $query->whereExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('stock_has_supplier_products as shs')
                ->whereColumn('shs.supplier_product_id', 'sp.id')
                ->where('shs.stock_id', $stockId)));
    }

    /**
     * @return array<int, array{supplier: string, to_discontinuing: int, to_active: int}>
     */
    public function preview(): array
    {
        $count = fn (Builder $query) => $query->groupBy('s.code')->selectRaw('s.code as supplier, count(*) as products')->pluck('products', 'supplier');

        $toDiscontinuing = $count($this->toDiscontinuing());
        $toActive        = $count($this->toRestore());

        return $toDiscontinuing->keys()->merge($toActive->keys())->unique()->sort()->values()
            ->map(fn (string $supplier) => [
                'supplier'         => $supplier,
                'to_discontinuing' => (int) ($toDiscontinuing[$supplier] ?? 0),
                'to_active'        => (int) ($toActive[$supplier] ?? 0),
            ])
            ->all();
    }

    public function handle(?int $stockId = null): int
    {
        $discontinuing = 0;
        $this->toDiscontinuing($stockId)->orderBy('sp.id')->select('sp.id')->chunkById(500, function ($rows) use (&$discontinuing) {
            foreach (SupplierProduct::whereIn('id', $rows->pluck('id'))->get() as $supplierProduct) {
                UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::DISCONTINUING->value], hydratorsDelay: 60, followsSkoStates: true);
                $discontinuing++;
            }
        }, 'sp.id', 'id');

        $this->restored = 0;
        $this->toRestore($stockId)->orderBy('sp.id')->select('sp.id')->chunkById(500, function ($rows) {
            foreach (SupplierProduct::whereIn('id', $rows->pluck('id'))->get() as $supplierProduct) {
                UpdateSupplierProduct::make()->action($supplierProduct, ['state' => SupplierProductStateEnum::ACTIVE->value], hydratorsDelay: 60, followsSkoStates: true);
                $this->restored++;
            }
        }, 'sp.id', 'id');

        return $discontinuing;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $preview = $this->preview();

        $command->table(['Supplier', 'To discontinuing', 'Back to active'], $preview);
        $command->info('Total: '.array_sum(array_column($preview, 'to_discontinuing')).' to discontinuing, '.array_sum(array_column($preview, 'to_active')).' back to active');

        if ($command->option('dry-run')) {
            $command->info('Dry run, nothing changed');

            return 0;
        }

        $command->info('Turned '.$this->handle().' discontinuing, '.$this->restored.' back to active');

        return 0;
    }
}
