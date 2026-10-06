<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 18:10:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Goods;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One-off: Aurora kept a carton barcode per organisation and they drifted apart (ES often ends in CT
 * where AW ends in C). Aiku keeps one per stock, so organisations are read in id order (AW, SK, ES,
 * Aroma) and the first one that has a barcode wins. Stocks that already carry one are left alone.
 */
class FetchStocksCartonBarcodesFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'stocks:fetch_carton_barcodes_from_aurora
        {--apply : Write the barcodes, without this the command only reports what it would do}';

    /**
     * @return array{chosen: array<int, string>, overruled: int}
     */
    public function handle(): array
    {
        $chosen    = [];
        $overruled = 0;

        foreach (Organisation::where('source->type', 'Aurora')->orderBy('id')->get() as $organisation) {
            $this->getOrganisationSource($organisation)->initialisation($organisation);

            $cartonBarcodes = DB::connection('aurora')->table('Part Dimension')
                ->where('Part Carton Barcode', '!=', '')
                ->pluck('Part Carton Barcode', 'Part SKU');

            $stockIds = DB::table('org_stocks')
                ->where('organisation_id', $organisation->id)
                ->whereNotNull('stock_id')
                ->whereIn('source_id', $cartonBarcodes->keys()->map(fn ($sku) => $organisation->id.':'.$sku))
                ->pluck('stock_id', 'source_id');

            foreach ($stockIds as $sourceId => $stockId) {
                $barcode = trim($cartonBarcodes[explode(':', $sourceId)[1]]);
                if (!isset($chosen[$stockId])) {
                    $chosen[$stockId] = $barcode;
                } elseif ($chosen[$stockId] !== $barcode) {
                    $overruled++;
                }
            }
        }

        $alreadySet = DB::table('stocks')->whereIn('id', array_keys($chosen))->whereNotNull('carton_barcode')->pluck('id')->all();

        return ['chosen' => array_diff_key($chosen, array_flip($alreadySet)), 'overruled' => $overruled];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        ['chosen' => $chosen, 'overruled' => $overruled] = $this->handle();

        $command->info(count($chosen).' stocks to get a carton barcode, '.$overruled.' organisation values overruled by an earlier organisation');

        if (!$command->option('apply')) {
            return 0;
        }

        foreach ($chosen as $stockId => $barcode) {
            DB::table('stocks')->where('id', $stockId)->whereNull('carton_barcode')->update(['carton_barcode' => $barcode]);
        }

        $command->info('Done');

        return 0;
    }
}
