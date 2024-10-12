<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 12 Oct 2022 17:56:45 Central European Summer Time, Benalmádena, Malaga Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Web\Webpage\StoreWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraWebpages extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:webpages {organisations?*} {--S|shop= : Shop slug} {--A|all= : import non online webpages as well} {--s|source_id=} {--d|db_suffix=} {--w|with=* : Accepted values: web-blocks}  {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Webpage
    {
        if ($webpageData = $organisationSource->fetchWebpage($organisationSourceId)) {
            if (empty($webpageData['webpage'])) {
                return null;
            }

            if ($webpage = Webpage::where('source_id', $webpageData['webpage']['source_id'])
                ->first()) {
                try {
                    $webpage = UpdateWebpage::make()->action(
                        webpage: $webpage,
                        modelData: $webpageData['webpage'],
                        hydratorsDelay: 60,
                        strict: false,
                        audit: false
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $webpageData['webpage'], 'Webpage', 'update');

                    return null;
                }
            } else {
                try {
                    $webpage = StoreWebpage::make()->action(
                        parent: $webpageData['website'],
                        modelData: $webpageData['webpage'],
                        hydratorsDelay: 60,
                        strict: false,
                        audit: false
                    );
                    Customer::enableAuditing();
                    $this->saveMigrationHistory(
                        $webpage,
                        Arr::except($webpageData['customer'], ['migration_data','parent_id','fetched_at','last_fetched_at'])
                    );
                    $this->recordNew($organisationSource);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $webpageData['webpage'], 'Webpage', 'store');

                    return null;
                }
            }


            if (in_array('web-blocks', $this->with)) {
                FetchAuroraWebBlocks::run($webpage, reset: true, dbSuffix: $this->dbSuffix);
            }


            $sourceData = explode(':', $webpage->source_id);
            DB::connection('aurora')->table('Page Store Dimension')
                ->where('Page Key', $sourceData[1])
                ->update(['aiku_id' => $webpage->id]);

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
        $query->whereNotIn('Webpage Code', ['shipping', 'privacy', 'returns', 'cookie_policy', 'showroom']);


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
        $query->whereNotIn('Webpage Code', ['shipping', 'privacy']);


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
