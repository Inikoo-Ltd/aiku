<?php

namespace App\Actions\Transfers\Aurora;

use App\Models\Catalogue\Product;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrphanTradeUnitLinks
{
    use AsAction;

    private AuroraOrganisationService $organisationSource;
    private bool $commit = false;

    public string $commandSignature = 'procurement:repair_orphan_trade_unit_links {organisation} {--commit : Apply the links, otherwise dry run}';

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $commit       = (bool)$command->option('commit');
        $this->commit = $commit;

        $this->organisationSource = new AuroraOrganisationService();
        $this->organisationSource->initialisation($organisation);

        $linked = 0;
        $skipped = 0;

        foreach ($this->orphanProducts($organisation) as $product) {
            $sourceData = explode(':', $product->source_id);
            if (count($sourceData) != 2 || $sourceData[0] != $organisation->id) {
                continue;
            }

            $bridge = DB::connection('aurora')->table('Product Part Bridge')
                ->where('Product Part Product ID', $sourceData[1])->get();

            if ($bridge->isEmpty()) {
                $command->line("SKIP product {$product->slug}: no aurora part bridge");
                $skipped++;
                continue;
            }

            $links = [];
            foreach ($bridge as $bridgeRow) {
                $tradeUnit = $this->tradeUnitFromPartSku($organisation, $bridgeRow->{'Product Part Part SKU'});
                if (!$tradeUnit) {
                    $links = null;
                    break;
                }
                $links[$tradeUnit->id] = ['quantity' => $bridgeRow->{'Product Part Ratio'}];
            }

            if (!$links) {
                $command->line("SKIP product {$product->slug}: trade unit missing for one of its aurora parts");
                $skipped++;
                continue;
            }

            $command->info(($commit ? 'LINK ' : 'WOULD LINK ')."product {$product->slug} -> ".json_encode($links));
            if ($commit) {
                $product->tradeUnits()->syncWithoutDetaching($links);
            }
            $linked++;
        }

        foreach ($this->orphanStocksQuery()->get() as $stock) {
            if ($this->isExcluded($stock->code) || !$stock->source_id) {
                continue;
            }
            $sourceData = explode(':', $stock->source_id);
            if (count($sourceData) != 2 || $sourceData[0] != $organisation->id) {
                continue;
            }
            $tradeUnit = $this->tradeUnitFromPartSku($organisation, $sourceData[1]);
            if (!$tradeUnit) {
                $command->line("SKIP stock {$stock->slug}: no trade unit for aurora part {$sourceData[1]}");
                $skipped++;
                continue;
            }
            $command->info(($commit ? 'LINK ' : 'WOULD LINK ')."stock {$stock->slug} -> trade unit {$tradeUnit->slug}");
            if ($commit) {
                $stock->tradeUnits()->syncWithoutDetaching([$tradeUnit->id => ['quantity' => 1]]);
                foreach (OrgStock::where('stock_id', $stock->id)->whereDoesntHave('tradeUnits')->get() as $orgStock) {
                    $orgStock->tradeUnits()->syncWithoutDetaching([$tradeUnit->id => ['quantity' => 1]]);
                    $command->info("LINK org stock {$orgStock->slug} -> trade unit {$tradeUnit->slug}");
                }
            }
            $linked++;
        }

        $command->info(($commit ? 'Linked' : 'Would link').": $linked, skipped: $skipped");

        return 0;
    }

    private function orphanProducts(Organisation $organisation)
    {
        return Product::whereNull('deleted_at')
            ->where('organisation_id', $organisation->id)
            ->whereNotNull('source_id')
            ->whereDoesntHave('tradeUnits')
            ->get();
    }

    private function orphanStocksQuery()
    {
        return Stock::whereNull('deleted_at')
            ->whereDoesntHave('tradeUnits');
    }

    private function isExcluded(string $code): bool
    {
        return str_contains($code, '-error') || str_contains($code, '-merged');
    }

    private function tradeUnitFromPartSku(Organisation $organisation, $partSku): ?TradeUnit
    {
        $part = DB::connection('aurora')->table('Part Dimension')->where('Part SKU', $partSku)->first();
        if (!$part) {
            return null;
        }

        if ($part->aiku_id ?? null) {
            $tradeUnit = TradeUnit::find($part->aiku_id);
            if ($tradeUnit) {
                return $tradeUnit;
            }
        }

        $reference = preg_replace('/\s/', '-', $part->{'Part Reference'});
        $tradeUnit = TradeUnit::where('source_slug', strtolower($reference))->first();

        if (!$tradeUnit && $this->commit) {
            $tradeUnit = FetchAuroraTradeUnits::run($this->organisationSource, $partSku);
        }

        return $tradeUnit;
    }
}
