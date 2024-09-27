<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:41:06 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Models\Dropshipping\CustomerClient;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraCustomerClients extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:customer-clients {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?CustomerClient
    {
        if ($customerClientData = $organisationSource->fetchCustomerClient($organisationSourceId)) {
            if ($customerClient = CustomerClient::withTrashed()->where('source_id', $customerClientData['customer_client']['source_id'])
                ->first()) {
                try {

                    $customerClient = UpdateCustomerClient::make()->action(
                        customerClient: $customerClient,
                        modelData: $customerClientData['customer_client'],
                        hydratorsDelay: 60,
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
                    $customerClient = StoreCustomerClient::make()->action(
                        customer: $customerClientData['customer'],
                        modelData: $customerClientData['customer_client'],
                        hydratorsDelay: 60,
                        strict: false,
                    );
                    $sourceData     = explode(':', $customerClient->source_id);
                    DB::connection('aurora')->table('Customer Client Dimension')
                        ->where('Customer Client Key', $sourceData[1])
                        ->update(['aiku_id' => $customerClient->id]);
                } catch (Exception $e) {
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
