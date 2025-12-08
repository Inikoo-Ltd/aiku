<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Feb 2023 14:38:21 Malaysia Time,  Ubud, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\Stock\UpdateStock;
use App\Models\Goods\Stock;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeletedStocks extends FetchAuroraAction
{
    use WithFetchStock;

    public string $commandSignature = 'fetch:deleted_stocks {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): array
    {
        $stock = null;
        $orgStock = null;

        if ($stockData = $organisationSource->fetchDeletedStock($organisationSourceId)) {
            if ($baseStock = Stock::withTrashed()->where('source_slug', $stockData['stock']['source_slug'])->first()) {
                if ($stock = Stock::withTrashed()->where('source_id', $stockData['stock']['source_id'])->first()) {
                    try {
                        $stock = UpdateStock::make()->action(
                            stock: $stock,
                            modelData: $stockData['stock'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );
                        $this->recordChange($organisationSource, $stock->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $stockData['stock'], 'DeletedStock', 'update');

                        return [
                            'stock' => null,
                            'orgStock' => null,
                        ];
                    }
                }
            } else {
                try {
                    $stock = StoreStock::make()->action(
                        parent: $organisationSource->getOrganisation()->group,
                        modelData: $stockData['stock'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    Stock::enableAuditing();
                    $this->saveMigrationHistory(
                        $stock,
                        Arr::except($stockData['stock'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->updateStockSources($stock, $stockData['stock']['source_id']);

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $stock->source_id);
                    DB::connection('aurora')->table('Part Deleted Dimension')
                        ->where('Part Deleted Key', $sourceData[1])
                        ->update(['aiku_id' => $stock->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $stockData['stock'], 'DeletedStock', 'store');

                    return [
                        'stock' => null,
                        'orgStock' => null,
                    ];
                }
            }

            $effectiveStock = $stock ?? $baseStock;

            if ($effectiveStock) {
                $orgStock = $this->processOrgStock($organisationSource, $effectiveStock, $stockData);
            }
        }

        return [
            'stock' => $stock,
            'orgStock' => $orgStock,
        ];
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Part Deleted Dimension')
            ->select('Part Deleted Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Part Deleted Dimension')->count();
    }
}
