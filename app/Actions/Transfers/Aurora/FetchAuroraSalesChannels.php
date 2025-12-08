<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 19:23:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Actions\Ordering\SalesChannel\UpdateSalesChannel;
use App\Models\Ordering\SalesChannel;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraSalesChannels extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:sales_channels {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    /**
     * @throws \Throwable
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?SalesChannel
    {
        $group = group();
        $salesChannelsData = $organisationSource->fetchSalesChannel($organisationSourceId);
        if (! $salesChannelsData) {
            return null;
        }

        $type = $salesChannelsData['sales_channel']['type'];
        if ($type->canSeed()) {
            $salesChannel = $group->salesChannels()->where('type', $type)->first();
            if (! $salesChannel) {
                dd('error can not find seed sales channel');
            }
            if (! $salesChannel->fetched_at) {
                $seededSalesChannelData = Arr::only($salesChannelsData['sales_channel'], [
                    'fetched_at',
                    'source_id',
                ]);
                UpdateSalesChannel::make()->action(
                    salesChannel: $salesChannel,
                    modelData: $seededSalesChannelData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                SalesChannel::enableAuditing();
                $this->saveMigrationHistory(
                    $salesChannel,
                    Arr::except($salesChannelsData['sales_channel'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );
                $sourceData = explode(':', $salesChannel->source_id);
                DB::connection('aurora')->table('Order Source Dimension')
                    ->where('Order Source Key', $sourceData[1])
                    ->update(['aiku_id' => $salesChannel->id]);
            } else {
                $seededSalesChannelData = Arr::only($salesChannelsData['sales_channel'], [
                    'last_fetched_at',
                ]);
                UpdateSalesChannel::make()->action(
                    salesChannel: $salesChannel,
                    modelData: $seededSalesChannelData,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            }
        } else {
            $salesChannel = $group->salesChannels()->where('code', $salesChannelsData['sales_channel']['code'])->first();
            if ($salesChannel) {
                UpdateSalesChannel::make()->action(
                    salesChannel: $salesChannel,
                    modelData: $salesChannelsData['sales_channel'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } else {

                $salesChannel = StoreSalesChannel::make()->action(
                    group: $group,
                    modelData: $salesChannelsData['sales_channel'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                SalesChannel::enableAuditing();
                $this->saveMigrationHistory(
                    $salesChannel,
                    Arr::except($salesChannelsData['sales_channel'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $sourceData = explode(':', $salesChannel->source_id);
                DB::connection('aurora')->table('Order Source Dimension')
                    ->where('Order Source Key', $sourceData[1])
                    ->update(['aiku_id' => $salesChannel->id]);
            }
        }

        $this->updateSalesChannelSources($salesChannel, $salesChannelsData['sales_channel']['source_id']);

        return $salesChannel;
    }

    public function updateSalesChannelSources(SalesChannel $salesChannel, string $source): void
    {
        $sources = Arr::get($salesChannel->sources, 'sales_channels', []);
        $sources[] = $source;
        $sources = array_unique($sources);

        $salesChannel->updateQuietly([
            'sources' => [
                'sales_channels' => $sources,
            ],
        ]);
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Order Source Dimension')
            ->select('Order Source Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Order Source Dimension')->count();
    }
}
