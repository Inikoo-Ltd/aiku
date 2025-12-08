<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Nov 2024 15:25:27 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Ordering\Purge\Hydrators\PurgeHydratePurgedOrders;
use App\Actions\Ordering\Purge\StorePurge;
use App\Actions\Ordering\Purge\UpdatePurge;
use App\Enums\Ordering\PurgedOrder\PurgedOrderStatusEnum;
use App\Models\Ordering\Purge;
use App\Models\Ordering\PurgedOrder;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPurges extends FetchAuroraAction
{
    use WithAuroraParsers;

    public string $commandSignature = 'fetch:purges {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Purge
    {
        $organisation = $organisationSource->getOrganisation();
        if ($purgeData = $organisationSource->fetchPurge($organisationSourceId)) {
            if ($purge = Purge::where('source_id', $purgeData['purge']['source_id'])->first()) {
                try {
                    $purge = UpdatePurge::make()->action(
                        purge: $purge,
                        modelData: $purgeData['purge'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $purge->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $purgeData['purge'], 'Purge', 'update');

                    return null;
                }
            } else {
                try {
                    $purge = StorePurge::make()->action(
                        shop: $purgeData['shop'],
                        modelData: $purgeData['purge'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Purge::enableAuditing();
                    $this->saveMigrationHistory(
                        $purge,
                        Arr::except($purgeData['purge'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $purge->source_id);
                    DB::connection('aurora')->table('Order Basket Purge Dimension')
                        ->where('Order Basket Purge Key', $sourceData[1])
                        ->update(['aiku_id' => $purge->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $purgeData['purge'], 'Purge', 'store');

                    return null;
                }
            }

            $purge->stats->update($purgeData['purge_stats']);

            $sourceData = explode(':', $purgeData['purge']['source_id']);
            foreach (
                DB::connection('aurora')->table('Order Basket Purge Order Fact')
                    ->where('Order Basket Purge Order Basket Purge Key', $sourceData[1])->get() as $purgedOrderAuroraData
            ) {
                $status = match ($purgedOrderAuroraData->{'Order Basket Purge Order Status'}) {
                    'In Process' => PurgedOrderStatusEnum::IN_PROCESS,
                    'Purged' => PurgedOrderStatusEnum::PURGED,
                    'Exculpated' => PurgedOrderStatusEnum::EXCULPATED,
                    'Cancelled' => PurgedOrderStatusEnum::CANCELLED,
                    default => PurgedOrderStatusEnum::ERROR
                };

                $purgedOrderData = [
                    'status' => $status,
                    'source_id' => $organisation->id.':'.$purgedOrderAuroraData->{'Order Basket Purge Order Basket Purge Key'}.'_'.$purgedOrderAuroraData->{'Order Basket Purge Order Order Key'},
                    'fetched_at' => now(),
                    'last_fetched_at' => now(),
                    'created_at' => $purge->created_at,
                ];

                if ($status == PurgedOrderStatusEnum::PURGED) {
                    $purgedOrderData['purged_at'] = $this->parseDatetime($purgedOrderAuroraData->{'Order Basket Purge Purged Date'});
                }

                if ($status == PurgedOrderStatusEnum::ERROR) {
                    $purgedOrderData['error_message'] = $purgedOrderAuroraData->{'Order Basket Purge Note'};
                }

                $purgedOrder = PurgedOrder::where('source_id', $purgedOrderData['source_id'])->first();
                if ($purgedOrder) {
                    $purgedOrder->update(Arr::except($purgedOrderData, 'fetch_at'));
                } else {
                    data_set($purgedOrderData, 'group_id', $purge->group_id);
                    data_set($purgedOrderData, 'organisation_id', $purge->organisation_id);
                    data_set($purgedOrderData, 'shop_id', $purge->shop_id);
                    $purge->purgedOrders()->create(
                        Arr::except($purgedOrderData, 'last_fetched_at')
                    );
                }
            }

            PurgeHydratePurgedOrders::run($purge);

            return $purge;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Order Basket Purge Dimension')
            ->select('Order Basket Purge Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Order Basket Purge Dimension')->count();
    }
}
