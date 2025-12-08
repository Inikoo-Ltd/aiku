<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Sept 2024 16:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Ordering\Adjustment\StoreAdjustment;
use App\Actions\Ordering\Adjustment\UpdateAdjustment;
use App\Models\Ordering\Adjustment;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraAdjustments extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:adjustments {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Adjustment
    {
        if ($adjustmentData = $organisationSource->fetchAdjustment($organisationSourceId)) {
            if ($adjustment = Adjustment::where('source_id', $adjustmentData['adjustment']['source_id'])->first()) {
                $adjustment = UpdateAdjustment::make()->action(
                    adjustment: $adjustment,
                    modelData: $adjustmentData['adjustment'],
                    hydratorsDelay: 30,
                    strict: false
                );
            } else {
                $adjustment = StoreAdjustment::make()->action(
                    shop: $adjustmentData['shop'],
                    modelData: $adjustmentData['adjustment'],
                    hydratorsDelay: 30,
                    strict: false
                );
            }

            return $adjustment;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Order No Product Transaction Fact')
            ->select('Order No Product Transaction Fact Key as source_id')
            ->where('Transaction Type', 'Adjustment')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Order No Product Transaction Fact')
            ->where('Transaction Type', 'Adjustment')->count();
    }
}
