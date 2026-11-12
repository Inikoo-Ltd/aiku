<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplierProducts\RepairOrgSupplierProductsSupplierDrift;
use App\Actions\SupplyChain\Supplier\Hydrators\SupplierHydrateSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Folds a duplicate supplier product into the record kept for the same product, so a duplicate supplier can then be
 * merged with MergeSuppliers. Every organisation keeps the product: its row on the duplicate takes the kept product
 * or, where the same org supplier already has a row for it, hands that row its SKO links, preferred flag, open
 * purchase order and delivery lines and active state. Finished orders and deliveries stay on the duplicate, which is
 * discontinued and renamed to free its code, never deleted.
 */
class MergeSupplierProducts
{
    use AsAction;

    public string $commandSignature = 'supplier_products:merge_duplicates
        {from : Id of the duplicate supplier whose products are retired}
        {to : Id of the supplier kept, which has the same product codes}
        {--fix : Merge, otherwise dry run}
        {--open-lines : Only move open purchase order and delivery lines left on products this supplier already merged}';

    public function refusal(SupplierProduct $from, SupplierProduct $to): ?string
    {
        if ($from->id === $to->id) {
            return 'same product';
        }

        if ($from->units_per_pack != $to->units_per_pack) {
            return "units per pack differ ($from->units_per_pack vs $to->units_per_pack)";
        }

        $missingTradeUnits = DB::table('model_has_trade_units')->where('model_type', 'SupplierProduct')->where('model_id', $from->id)
            ->whereNotIn('trade_unit_id', DB::table('model_has_trade_units')->where('model_type', 'SupplierProduct')->where('model_id', $to->id)->select('trade_unit_id'))
            ->exists();
        if ($missingTradeUnits) {
            return 'trade units differ';
        }

        $missingStocks = StockHasSupplierProduct::where('supplier_product_id', $from->id)
            ->whereNotIn('stock_id', StockHasSupplierProduct::where('supplier_product_id', $to->id)->select('stock_id'))
            ->exists();

        return $missingStocks ? 'stocks differ' : null;
    }

    /**
     * @return array<int, int> ids of the org suppliers touched
     *
     * @throws \Throwable
     */
    public function handle(SupplierProduct $from, SupplierProduct $to): array
    {
        return DB::transaction(function () use ($from, $to) {
            $touchedOrgSuppliers = [];

            foreach ($from->orgSupplierProducts()->get() as $orgSupplierProduct) {
                $touchedOrgSuppliers[$orgSupplierProduct->org_supplier_id] = $orgSupplierProduct->org_supplier_id;

                $twin = OrgSupplierProduct::where('organisation_id', $orgSupplierProduct->organisation_id)
                    ->where('supplier_product_id', $to->id)
                    ->orderByRaw('org_supplier_id = ? desc', [$orgSupplierProduct->org_supplier_id])
                    ->first();

                if (!$twin) {
                    $orgSupplierProduct->update(['supplier_product_id' => $to->id]);
                    $this->moveStockLinks($orgSupplierProduct, $orgSupplierProduct, $to);

                    continue;
                }

                $touchedOrgSuppliers[$twin->org_supplier_id] = $twin->org_supplier_id;

                if ($orgSupplierProduct->state === OrgSupplierProductStateEnum::ACTIVE->value) {
                    $twin->update(['state' => OrgSupplierProductStateEnum::ACTIVE, 'is_available' => $orgSupplierProduct->is_available]);
                }

                $this->moveStockLinks($orgSupplierProduct, $twin, $to);
                RepairOrgSupplierProductsSupplierDrift::make()->mergeIntoTwin($orgSupplierProduct, $twin);
            }

            $this->moveOpenLines($from, $to);

            StockHasSupplierProduct::where('supplier_product_id', $from->id)
                ->whereNotIn('id', OrgStockHasOrgSupplierProduct::whereNotNull('stock_has_supplier_product_id')->select('stock_has_supplier_product_id'))
                ->delete();

            if ($from->state === SupplierProductStateEnum::ACTIVE && $to->state !== SupplierProductStateEnum::ACTIVE) {
                $to->update(['state' => SupplierProductStateEnum::ACTIVE, 'is_available' => $from->is_available]);
            }

            $from->update([
                'code'         => $from->code.'-merged',
                'state'        => SupplierProductStateEnum::DISCONTINUED,
                'is_available' => false,
                'data'         => [...($from->data ?? []), 'merged_into_supplier_product_id' => $to->id],
            ]);

            return $touchedOrgSuppliers;
        });
    }

    /**
     * Purchase orders and deliveries still on their way receive the kept product, so the goods and their costing
     * land on it. Finished ones keep the duplicate, as recorded.
     */
    public function moveOpenLines(SupplierProduct $from, SupplierProduct $to, bool $fix = true): int
    {
        $openPurchaseOrderLines = PurchaseOrderTransaction::where('supplier_product_id', $from->id)
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('state', PurchaseOrderStateEnum::live()));

        $openDeliveryLines = StockDeliveryItem::where('supplier_product_id', $from->id)
            ->whereHas('stockDelivery', fn ($query) => $query->whereNotIn('state', [
                StockDeliveryStateEnum::PLACED,
                StockDeliveryStateEnum::CANCELLED,
                StockDeliveryStateEnum::NOT_RECEIVED,
            ]));

        if (!$fix) {
            return $openPurchaseOrderLines->count() + $openDeliveryLines->count();
        }

        return $openPurchaseOrderLines->update(['supplier_product_id' => $to->id]) + $openDeliveryLines->update(['supplier_product_id' => $to->id]);
    }

    /**
     * SKO links follow the row the organisation keeps buying through, on the kept product's stock link. Where that
     * row is already linked to the SKO, the link with the higher priority wins, so a preferred supplier stays preferred.
     */
    private function moveStockLinks(OrgSupplierProduct $orgSupplierProduct, OrgSupplierProduct $keptOrgSupplierProduct, SupplierProduct $to): void
    {
        foreach (OrgStockHasOrgSupplierProduct::with('orgStock')->where('org_supplier_product_id', $orgSupplierProduct->id)->get() as $link) {
            $stockLink = StockHasSupplierProduct::firstOrCreate(
                ['stock_id' => $link->orgStock->stock_id, 'supplier_product_id' => $to->id],
                ['available' => true]
            );

            $existing = $orgSupplierProduct->id === $keptOrgSupplierProduct->id ? null : OrgStockHasOrgSupplierProduct::where('org_stock_id', $link->org_stock_id)
                ->where('org_supplier_product_id', $keptOrgSupplierProduct->id)
                ->first();

            if (!$existing) {
                $link->update(['org_supplier_product_id' => $keptOrgSupplierProduct->id, 'stock_has_supplier_product_id' => $stockLink->id]);

                continue;
            }

            if ($link->local_priority > $existing->local_priority) {
                $existing->update(['local_priority' => $link->local_priority]);
            }
            $link->delete();
        }
    }

    /**
     * @return array{merged: int, refused: array<string, string>}
     *
     * @throws \Throwable
     */
    public function mergeSupplier(Supplier $from, Supplier $to, bool $fix = false): array
    {
        $merged              = 0;
        $refused             = [];
        $touchedOrgSuppliers = [];

        foreach ($from->supplierProducts()->get() as $fromProduct) {
            $toProduct = $to->supplierProducts()->whereRaw('lower(code) = ?', [mb_strtolower($fromProduct->code)])->first();
            if (!$toProduct) {
                continue;
            }

            if ($reason = $this->refusal($fromProduct, $toProduct)) {
                $refused[$fromProduct->code] = $reason;

                continue;
            }

            $merged++;
            if ($fix) {
                $touchedOrgSuppliers += $this->handle($fromProduct, $toProduct);
            }
        }

        if ($fix) {
            $organisationIds = OrgSupplier::whereIn('id', $touchedOrgSuppliers)->pluck('organisation_id')->unique();
            foreach (OrgSupplier::whereIn('id', $touchedOrgSuppliers)->get() as $orgSupplier) {
                OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
            }
            foreach (Organisation::whereIn('id', $organisationIds)->get() as $organisation) {
                OrganisationHydrateOrgSupplierProducts::run($organisation);
            }
            SupplierHydrateSupplierProducts::run($from);
            SupplierHydrateSupplierProducts::run($to);
        }

        return ['merged' => $merged, 'refused' => $refused];
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $from = Supplier::find($command->argument('from'));
        $to   = Supplier::find($command->argument('to'));

        if (!$from || !$to || $from->id === $to->id) {
            $command->error('from and to must be the ids of two different suppliers');

            return Command::FAILURE;
        }

        $fix = (bool) $command->option('fix');

        if ($command->option('open-lines')) {
            $moved = 0;
            foreach ($from->supplierProducts()->whereNotNull('data->merged_into_supplier_product_id')->get() as $merged) {
                $kept = SupplierProduct::find($merged->data['merged_into_supplier_product_id']);
                if ($kept?->supplier_id === $to->id) {
                    $moved += $this->moveOpenLines($merged, $kept, $fix);
                }
            }
            $command->info(($fix ? 'Moved' : 'Would move')." $moved open lines to $to->code products");

            return Command::SUCCESS;
        }

        $result = $this->mergeSupplier($from, $to, $fix);

        $command->info(($fix ? 'Merged' : 'Would merge')." {$result['merged']} products of $from->code into $to->code");
        if ($result['refused']) {
            $command->warn('Refused, left as they are:');
            $command->table(['code', 'reason'], collect($result['refused'])->map(fn ($reason, $code) => [$code, $reason])->values()->all());
        }

        return Command::SUCCESS;
    }
}
