<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jun 2024 15:40:49 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPortfolios extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:portfolios {organisations?*} {--s|source_id=} {--d|db_suffix=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new} ';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Portfolio
    {
        $portfolioData = $organisationSource->fetchPortfolio($organisationSourceId);

        if ($portfolioData) {
            if ($portfolio = Portfolio::where('source_id', $portfolioData['portfolio']['source_id'])->first()) {
                try {
                    $portfolio = UpdatePortfolio::make()->action(
                        portfolio: $portfolio,
                        modelData: $portfolioData['portfolio'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $portfolio->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $portfolioData['portfolio'], 'Portfolio', 'update');

                    return null;
                }
            } else {
                try {
                    $customer             = $portfolioData['customer'];
                    $platform             = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
                    $customerSalesChannel = $customer->customerSalesChannels()
                        ->where('platform_id', $platform->id)
                        ->first();
                    if (!$customerSalesChannel) {
                        $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
                            customer: $customer,
                            platform: $platform,
                            modelData: [
                                'reference' => (string)$customer->id,
                            ],
                            hydratorsDelay: $this->hydratorsDelay
                        );
                    }


                    $portfolio = StorePortfolio::make()->action(
                        customerSalesChannel: $customerSalesChannel,
                        item: $portfolioData['product'],
                        modelData: $portfolioData['portfolio'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    Portfolio::enableAuditing();
                    $this->saveMigrationHistory(
                        $portfolio,
                        Arr::except($portfolioData['portfolio'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $portfolio->source_id);

                    DB::connection('aurora')->table('Customer Portfolio Fact')
                        ->where('Customer Portfolio Key', $sourceData[1])
                        ->update(['aiku_id' => $portfolio->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $portfolioData['portfolio'], 'Portfolio', 'store');

                    return null;
                }
            }


            return $portfolio;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Customer Portfolio Fact')
            ->select('Customer Portfolio Key as source_id')
            ->orderBy('source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Portfolio Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Customer Portfolio Fact');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Portfolio Store Key', $sourceData[1]);
        }

        return $query->count();
    }


}
