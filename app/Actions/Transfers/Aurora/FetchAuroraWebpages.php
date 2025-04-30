<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 12 Oct 2022 17:56:45 Central European Summer Time, Benalmádena, Malaga Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Models\Web\Webpage;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraWebpages extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:webpages {organisations?*} {--S|shop= : Shop slug} {--A|all= : import non online webpages as well} {--s|source_id=} {--d|db_suffix=} {--w|with=* : Accepted values: web_blocks}  {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Webpage
    {
        if ($webpageData = $organisationSource->fetchWebpage($organisationSourceId)) {
            if (empty($webpageData['webpage'])) {
                return null;
            }

            $isHone = false;
            if (Arr::get($webpageData, 'is_home_logged_out') || Arr::get($webpageData, 'is_home_logged_in')) {
                $webpage = $webpageData['website']->storefront;
                $isHone  = true;
            } else {
                $webpage = Webpage::where('source_id', $webpageData['webpage']['source_id'])->first();
            }

            if ($webpage) {

                if (!$webpage->allow_fetch) {
                    return $webpage;
                }

                try {
                    if ($webpage->is_fixed) {
                        data_forget($webpageData, 'webpage.code');
                        data_forget($webpageData, 'webpage.url');
                    }

                    if ($isHone) {
                        $migrationData = $webpage->migration_data;
                        $migrationData = array_merge($migrationData, Arr::get($webpageData, 'webpage.migration_data', []));
                        data_set($webpageData, 'webpage.migration_data', $migrationData);

                        if (Arr::get($webpageData, 'is_home_logged_out')) {
                            $migrationData['webpage'] = [];
                            $migrationData['webpage'] = [
                                'migration_data' => $migrationData
                            ];
                        }
                    }

                    $lastPublishedAt = Arr::get($webpage->migration_data, 'webpage.last_published_at');
                    if ($lastPublishedAt) {
                        $lastPublishedAt = Carbon::parse($lastPublishedAt);
                    }

                    $webpage = UpdateWebpage::make()->action(
                        webpage: $webpage,
                        modelData: $webpageData['webpage'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    if (in_array('web_blocks', $this->with)) {
                        FetchAuroraWebBlocks::run($organisationSource, $webpage, reset: false, dbSuffix: $this->dbSuffix);
                        $currentPublishedAt = Arr::get($webpage->migration_data, 'webpage.last_published_at');
                        if ($currentPublishedAt) {
                            $currentPublishedAt = Carbon::parse($currentPublishedAt);
                        }

                        if (!$lastPublishedAt and $currentPublishedAt and $currentPublishedAt->gt($lastPublishedAt)) {
                            PublishWebpage::make()->action(
                                $webpage,
                                [
                                    'comment' => 'Published in aurora',
                                ]
                            );
                        }
                    }
                    $this->recordChange($organisationSource, $webpage->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $webpageData['webpage'], 'Webpage', 'update');

                    return null;
                }
            } else {
                if (Arr::get($webpageData, 'is_home_logout')) {
                    return null;
                }

                try {
                    $webpage = StoreWebpage::make()->action(
                        parent: $webpageData['website'],
                        modelData: $webpageData['webpage'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    Webpage::enableAuditing();

                    if (in_array('web_blocks', $this->with)) {

                        FetchAuroraWebBlocks::run($organisationSource, $webpage, reset: false, dbSuffix: $this->dbSuffix);

                        PublishWebpage::make()->action(
                            $webpage,
                            [
                                'comment' => 'Initial publish after migration',
                            ]
                        );
                    }


                    $this->saveMigrationHistory(
                        $webpage,
                        Arr::except($webpageData['webpage'], ['migration_data', 'parent_id', 'fetched_at', 'last_fetched_at'])
                    );
                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $webpage->source_id);
                    DB::connection('aurora')->table('Page Store Dimension')
                        ->where('Page Key', $sourceData[1])
                        ->update(['aiku_id' => $webpage->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $webpageData['webpage'], 'Webpage', 'store');

                    return null;
                }
            }

            return $webpage;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Page Store Dimension')
            ->join('Website Dimension', 'Website Dimension.Website Key', '=', 'Page Store Dimension.Webpage Website Key')
            ->select('Page Key as source_id')
            ->where('Page Store Dimension.aiku_ignore', 'No')
            ->orderBy('source_id');


        $query->where('Website Status', 'Active');
        $query->where('Webpage State', 'Online');


        if ($this->onlyNew) {
            $query->whereNull('Page Store Dimension.aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Webpage Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Page Store Dimension')
            ->join('Website Dimension', 'Website Dimension.Website Key', '=', 'Page Store Dimension.Webpage Website Key')
            ->where('aiku_ignore', 'No');
        if ($this->onlyNew) {
            $query->whereNull('Page Store Dimension.aiku_id');
        }

        $query->where('Website Status', 'Active');
        $query->where('Webpage State', 'Online');


        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Webpage Store Key', $sourceData[1]);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Page Store Dimension')->update(['aiku_id' => null]);
    }
}
