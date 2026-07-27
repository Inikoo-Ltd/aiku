<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Moves the IAL01 "Import Address Labels" picking hack off the product bills of materials and onto
 * `org_stocks.consumables`, so the packer is told how many labels to add without the label being
 * modelled as fake stock. Only writes the consumable, removing the bill of materials lines is a
 * separate step that must not run until pickers and packers have confirmed they see the new banner.
 *
 * The target org stocks are those reachable from a product that carries the label, minus the label's
 * own org stock — a picker fetches the real goods, the packer adds the label to the box afterwards.
 *
 * Organisations that have never dispatched the label are excluded. At least one master carries the
 * label in its own bill of materials and cascaded it to children in other organisations, giving them
 * bill of materials lines their warehouses have never acted on. Those stray lines die with the master
 * bill of materials rather than being carried into the replacement.
 */
class RepairIal01OrgStockConsumables
{
    use AsAction;

    private const string CONSUMABLE_CODE = 'IAL01';

    public string $commandSignature = 'inventory:repair-ial01-org-stock-consumables
        {--apply : Write the consumables, without this the command only reports what it would do}';

    public function getTradeUnit(): ?TradeUnit
    {
        return TradeUnit::where('code', self::CONSUMABLE_CODE)->first();
    }

    /**
     * Org stocks holding the label itself, which must never be given the consumable.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getLabelOrgStockIds(TradeUnit $tradeUnit)
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'OrgStock')
            ->where('trade_unit_id', $tradeUnit->id)
            ->distinct()
            ->pluck('model_id');
    }

    /**
     * Organisations whose warehouses have actually dispatched the label.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getDispatchingOrganisationIds(TradeUnit $tradeUnit)
    {
        return DB::table('delivery_note_items')
            ->whereIn('delivery_note_items.org_stock_id', $this->getLabelOrgStockIds($tradeUnit))
            ->distinct()
            ->pluck('delivery_note_items.organisation_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getOrgStocks(TradeUnit $tradeUnit)
    {
        return DB::table('org_stocks')
            ->join('product_has_org_stocks', 'product_has_org_stocks.org_stock_id', '=', 'org_stocks.id')
            ->join('products', 'products.id', '=', 'product_has_org_stocks.product_id')
            ->join('model_has_trade_units', function ($join) use ($tradeUnit) {
                $join->on('model_has_trade_units.model_id', '=', 'products.id')
                    ->where('model_has_trade_units.model_type', 'Product')
                    ->where('model_has_trade_units.trade_unit_id', $tradeUnit->id);
            })
            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
            ->whereNotIn('org_stocks.id', $this->getLabelOrgStockIds($tradeUnit))
            ->whereIn('org_stocks.organisation_id', $this->getDispatchingOrganisationIds($tradeUnit))
            ->groupBy('org_stocks.id', 'org_stocks.code', 'org_stocks.state', 'org_stocks.consumables', 'organisations.code')
            ->select([
                'org_stocks.id',
                'org_stocks.code',
                'org_stocks.state',
                'org_stocks.consumables',
                'organisations.code as organisation_code',
                DB::raw('max(model_has_trade_units.quantity) as quantity'),
                DB::raw('count(distinct products.id) as products'),
            ])
            ->orderBy('organisations.code')
            ->orderBy('org_stocks.code')
            ->get();
    }

    public function handle(bool $apply = false): array
    {
        $tradeUnit = $this->getTradeUnit();

        if (!$tradeUnit) {
            return ['org_stocks' => 0, 'written' => 0, 'already_set' => 0, 'rows' => collect()];
        }

        $orgStocks  = $this->getOrgStocks($tradeUnit);
        $written    = 0;
        $alreadySet = 0;

        foreach ($orgStocks as $orgStock) {
            $consumables = [
                [
                    'code'     => self::CONSUMABLE_CODE,
                    'quantity' => (float) $orgStock->quantity,
                ],
            ];

            if (json_decode((string) $orgStock->consumables, true) == $consumables) {
                $alreadySet++;
                continue;
            }

            if ($apply) {
                DB::table('org_stocks')->where('id', $orgStock->id)->update([
                    'consumables' => json_encode($consumables),
                ]);
            }

            $written++;
        }

        return [
            'org_stocks'  => $orgStocks->count(),
            'written'     => $written,
            'already_set' => $alreadySet,
            'rows'        => $orgStocks,
        ];
    }

    public function asCommand(Command $command): int
    {
        $apply = (bool) $command->option('apply');

        if (!$this->getTradeUnit()) {
            $command->error('Trade unit '.self::CONSUMABLE_CODE.' not found, nothing to do.');

            return Command::FAILURE;
        }

        $result = $this->handle($apply);

        $byOrganisation = collect($result['rows'])->groupBy('organisation_code')->map(fn ($rows) => [
            $rows->first()->organisation_code,
            $rows->count(),
            $rows->where('state', 'active')->count(),
            $rows->sum('products'),
            $rows->pluck('quantity')->unique()->sort()->implode(', '),
        ]);

        $command->table(['Organisation', 'SKOs', 'Active', 'Products', 'Quantities'], $byOrganisation);

        $command->info(($apply ? 'Written' : 'Would write').': '.$result['written']
            .', already correct: '.$result['already_set']
            .', total SKOs needing '.self::CONSUMABLE_CODE.': '.$result['org_stocks']);

        if (!$apply) {
            $command->warn('Dry run, nothing was written. Re-run with --apply to write.');
        }

        return Command::SUCCESS;
    }
}
