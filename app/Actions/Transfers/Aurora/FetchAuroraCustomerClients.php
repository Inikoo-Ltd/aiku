<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:41:06 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraCustomerClients extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:customer_clients {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?CustomerClient
    {
        $customerClientData = $organisationSource->fetchCustomerClient($organisationSourceId);
        if ($customerClientData) {
            if ($customerClient = CustomerClient::withTrashed()->where('source_id', $customerClientData['customer_client']['source_id'])
                ->first()) {
                try {
                    $customerClient = UpdateCustomerClient::make()->action(
                        customerClient: $customerClient,
                        modelData: $customerClientData['customer_client'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $customerClient->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $customerClientData['customer_client'], 'CustomerClient', 'update');

                    return null;
                }
            } else {
                try {

                    $customer = $customerClientData['customer_sales_channel'];
                    $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
                    $customerSalesChannel = $customer->customerSalesChannels()
                        ->where('platform_id', $platform->id)
                        ->first();
                    if (!$customerSalesChannel) {
                        $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
                            customer: $customer,
                            platform: $platform,
                            modelData: [
                                'reference' => (string) $customer->id,
                            ],
                            hydratorsDelay: $this->hydratorsDelay
                        );
                    }

                    $customerClient = StoreCustomerClient::make()->action(
                        customerSalesChannel: $customerSalesChannel,
                        modelData: $customerClientData['customer_client'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    CustomerClient::enableAuditing();
                    $this->saveMigrationHistory(
                        $customerClient,
                        Arr::except($customerClientData['customer_client'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $customerClient->source_id);
                    DB::connection('aurora')->table('Customer Client Dimension')
                        ->where('Customer Client Key', $sourceData[1])
                        ->update(['aiku_id' => $customerClient->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $customerClientData['customer_client'], 'CustomerClient', 'store');
                    return null;
                }
            }


            return $customerClient;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Customer Client Dimension')
            ->select('Customer Client Key as source_id')
            ->orderBy('source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Client Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Customer Client Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Client Store Key', $sourceData[1]);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Customer Client Dimension')->update(['aiku_id' => null]);
    }

}
