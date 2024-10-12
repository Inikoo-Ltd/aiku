<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:32:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */


/** @noinspection PhpUnused */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\Helpers\Attachment\SaveModelAttachment;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\CRM\Customer;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraCustomers extends FetchAuroraAction
{
    use WithAuroraAttachments;
    use WithAuroraParsers;

    public string $commandSignature = 'fetch:customers {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--w|with=* : Accepted values: clients orders web-users attachments portfolio favourites full} {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Customer
    {
        $with = $this->with;

        if ($customerData = $organisationSource->fetchCustomer($organisationSourceId)) {

            if ($customer = Customer::withTrashed()->where('source_id', $customerData['customer']['source_id'])
                ->first()) {
                try {
                    $customer = UpdateCustomer::make()->action(
                        customer: $customer,
                        modelData: $customerData['customer'],
                        hydratorsDelay: 60,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $customer->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $customerData['customer'], 'Customer', 'update');

                    return null;
                }
            } else {
                try {
                    $customer = StoreCustomer::make()->action(
                        shop: $customerData['shop'],
                        modelData: $customerData['customer'],
                        hydratorsDelay: $this->hydrateDelay,
                        strict: false,
                        audit: false
                    );

                    Customer::enableAuditing();

                    //
                    //  dd($customerData['customer']);

                    $this->saveMigrationHistory(
                        $customer,
                        Arr::except($customerData['customer'], ['fetched_at','last_fetched_at'])
                    );


                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $customer->source_id);
                    DB::connection('aurora')->table('Customer Dimension')
                        ->where('Customer Key', $sourceData[1])
                        ->update(['aiku_id' => $customer->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $customerData['customer'], 'Customer', 'store');

                    return null;
                }
            }


            $sourceData = explode(':', $customer->source_id);

            if (in_array('products', $with) || in_array('full', $with)) {
                foreach (
                    DB::connection('aurora')
                        ->table('Product Dimension')
                        ->where('Product Customer Key', $sourceData[1])
                        ->select('Product ID as source_id')
                        ->orderBy('source_id')->get() as $product
                ) {
                    FetchAuroraProducts::run($organisationSource, $product->source_id);
                }
            }

            if (in_array('favourites', $with) || in_array('full', $with)) {
                foreach (
                    DB::connection('aurora')
                        ->table('Customer Favourite Product Fact')
                        ->where('Customer Favourite Product Customer Key', $sourceData[1])
                        ->select('Customer Favourite Product Key as source_id')
                        ->orderBy('source_id')->get() as $favourite
                ) {
                    FetchAuroraFavourites::run($organisationSource, $favourite->source_id);
                }
            }


            if ($customer->shop->type == ShopTypeEnum::DROPSHIPPING and
                (
                    in_array('clients', $with) || in_array('full', $with)
                )

            ) {
                foreach (
                    DB::connection('aurora')
                        ->table('Customer Client Dimension')
                        ->where('Customer Client Customer Key', $sourceData[1])
                        ->select('Customer Client Key as source_id')
                        ->orderBy('source_id')->get() as $customerClient
                ) {
                    FetchAuroraCustomerClients::run($organisationSource, $customerClient->source_id);
                }
            }

            if ($customer->shop->type == ShopTypeEnum::DROPSHIPPING and
                (
                    in_array('portfolio', $with) || in_array('full', $with)
                )
            ) {
                foreach (
                    DB::connection('aurora')
                        ->table('Customer Portfolio Fact')
                        ->where('Customer Portfolio Customer Key', $sourceData[1])
                        ->select('Customer Portfolio Key as source_id')
                        ->orderBy('source_id')->get() as $portfolio
                ) {
                    FetchAuroraPortfolios::run($organisationSource, $portfolio->source_id);
                }
            }

            if (in_array('orders', $with) || in_array('full', $with)) {
                foreach (
                    DB::connection('aurora')
                        ->table('Order Dimension')
                        ->where('Order Customer Key', $sourceData[1])
                        ->select('Order Key as source_id')
                        ->orderBy('source_id')->get() as $order
                ) {
                    FetchAuroraOrders::run($organisationSource, $order->source_id, true);
                }
            }


            if (in_array('web-users', $with) || in_array('full', $with)) {
                foreach (
                    DB::connection('aurora')
                        ->table('Website User Dimension')
                        ->where('Website User Customer Key', $sourceData[1])
                        ->select('Website User Key as source_id')
                        ->orderBy('source_id')->get() as $webUserData
                ) {
                    FetchAuroraWebUsers::run($organisationSource, $webUserData->source_id);
                }
            }


            if (in_array('attachments', $this->with) || in_array('full', $with)) {
                $sourceData = explode(':', $customer->source_id);
                foreach ($this->parseAttachments($sourceData[1]) ?? [] as $attachmentData) {
                    SaveModelAttachment::run(
                        $customer,
                        $attachmentData['fileData'],
                        $attachmentData['modelData'],
                    );
                    $attachmentData['temporaryDirectory']->delete();
                }
            }


            return $customer;
        }

        return null;
    }

    private function parseAttachments($staffKey): array
    {
        $attachments = $this->getModelAttachmentsCollection(
            'Customer',
            $staffKey
        )->map(function ($auroraAttachment) {
            return $this->fetchAttachment($auroraAttachment);
        });

        return $attachments->toArray();
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Customer Dimension')
            ->select('Customer Key as source_id')
            ->orderBy('Customer First Contacted Date');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Customer Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Store Key', $sourceData[1]);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Customer Dimension')->update(['aiku_id' => null]);
    }
}
