<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 00:35:52 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Transfers\FetchAction;
use App\Enums\Helpers\Fetch\FetchTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FetchAuroraAction extends FetchAction
{
    use WithAuroraOrganisationsArgument;

    public string $jobQueue = 'aurora';
    public int $jobTimeout = 60 * 60 * 12;

    protected function preProcessCommand(Command $command): void
    {
        $this->dbSuffix = $command->option('db_suffix') ?? '';

        if ($command->getName() == 'fetch:webpages') {
            $this->fetchAll = (bool)$command->option('all');
        }


        if (in_array($command->getName(), [
                'fetch:orders',
                'fetch:invoices',
                'fetch:delivery_notes',
                'fetch:dispatched_emails',
                'fetch:email_tracking_events',
                'fetch:histories'
            ]) and $command->option('order')) {
            $this->orderDesc = (bool)$command->option('order') == 'desc';
        }

        if (in_array($command->getName(), [
                'fetch:orders',
                'fetch:invoices',
                'fetch:delivery_notes',
            ]) and $command->option('only_orders_no_transactions')) {
            $this->onlyOrdersNoTransactions = (bool)$command->option('only_orders_no_transactions');
        }

        if (in_array($command->getName(), [
                'fetch:orders',
                'fetch:invoices',
                'fetch:delivery_notes',
                'fetch:dispatched_emails',
                'fetch:email_tracking_events',
                'fetch:histories'
            ]) and $command->option('days')) {
            $this->fromDays = (int)$command->option('days');
        }


        if (in_array($command->getName(), [
                'fetch:customers',
                'fetch:web_users',
                'fetch:products',
                'fetch:webpages',
                'fetch:invoices',
                'fetch:orders',
                'fetch:delivery_notes',
                'fetch:products',
                'fetch:services',
                'fetch:portfolios',
                'fetch:favourites',
                'fetch:offer_components'
            ]) and $command->option('shop')) {
            $this->shop = Shop::where('slug', $command->option('shop'))->firstOrFail();
        }

        if (in_array($command->getName(), [
            'fetch:stocks',
            'fetch:products',
            'fetch:services',
            'fetch:orders',
            'fetch:invoices',
            'fetch:customers',
            'fetch:customer_clients',
            'fetch:delivery_notes',
            'fetch:purchase-orders',
            'fetch:suppliers',
            'fetch:web_users',
            'fetch:prospects',
            'fetch:deleted_customers',
            'fetch:webpages',
            'fetch:supplier-products',
            'fetch:payments',
            'fetch:pallets',
            'fetch:families',
            'fetch:departments',
            'fetch:portfolios',
            'fetch:stock_movements',
            'fetch:deleted_locations',
            'fetch:customer_notes',
            'fetch:histories',
            'fetch:uploads',
            'fetch:favourites',
            'fetch:stock-deliveries',
            'fetch:mailshots',
            'fetch:dispatched_emails',
            'fetch:email_tracking_events',
            'fetch:queries',
            'fetch:subscription_events',
            'fetch:offer_components',
            'fetch:credits'
        ])) {
            $this->onlyNew = (bool)$command->option('only_new');
        }


        if (in_array($command->getName(), [
            'fetch:customers',
            'fetch:orders',
            'fetch:invoices',
            'fetch:delivery_notes',
            'fetch:purchase_orders',
            'fetch:stock_deliveries',
            'fetch:webpages',
            'fetch:dispatched_emails'
        ])) {
            $this->with = $command->option('with');
        }

        if ($command->getName() == 'fetch:orders') {
            $this->basket = $command->option('basket') ?? false;
        }

        if ($command->getName() == 'fetch:histories' and $command->option('model')) {
            $model = match ($command->option('model')) {
                'Customer' => ['Customer'],
                'Prospect' => ['Prospect'],
                'Location' => ['Location'],
                'Product' => ['Product'],
                'WarehouseArea' => ['Warehouse Area'],
                default => null
            };

            if (!$model) {
                $command->error('Invalid history model');
                exit();
            } else {
                $command->info('Fetching histories for: '.implode(',', $model));
            }


            $this->model = $model;
        }
    }

    protected function doReset(Command $command): void
    {
        if (in_array($command->getName(), [
                'fetch:stocks',
                'fetch:products',
                'fetch:orders',
                'fetch:invoices',
                'fetch:customers',
                'fetch:customers-clients',
                'fetch:web_users',
                'fetch:delivery_notes',
                'fetch:purchase-orders',
                'fetch:web_users',
                'fetch:prospects',
                'fetch:deleted_customers',
                'fetch:webpages'
            ]) and $command->option('reset')) {
            $this->reset();
        }
    }

    public function getDBPrefix(Command $command): string
    {
        return $command->option('db_suffix') ?? '';
    }


    public function getJobUniqueId(int $organisationID, int $organisationSourceId, array $with): string
    {
        return $organisationID.'-'.$organisationSourceId.'-'.implode('-', $with);
    }

    /**
     * @throws \Exception
     */
    public function asJob(int $organisationID, int $organisationSourceId, array $with): void
    {
        $organisation = Organisation::find($organisationID);
        if (!$organisation) {
            throw new \Exception('Invalid Organisation ID');
        }

        $this->with               = $with;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);

        $this->handle($this->organisationSource, $organisationSourceId);
    }

    /**
     * @throws \Exception
     */
    public function action(int $organisationID, int $organisationSourceId, array $with): Model|array|null
    {
        $organisation = Organisation::find($organisationID);
        if (!$organisation) {
            throw new \Exception('Invalid Organisation ID');
        }

        $this->with               = $with;
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);

        return $this->handle($this->organisationSource, $organisationSourceId);
    }


    public function jsonResponse($model): array
    {
        if ($model) {
            return [
                'model'     => $model->getMorphClass(),
                'id'        => $model->id,
                'source_id' => $model->source_id,
            ];
        } else {
            return [
                'error' => 'model not returned'
            ];
        }
    }

    protected function getFetchType(Command $command): FetchTypeEnum
    {
        return match ($command->getName()) {
            'fetch:prospects' => FetchTypeEnum::PROSPECTS,
            'fetch:invoices' => FetchTypeEnum::INVOICES,
            'fetch:locations' => FetchTypeEnum::LOCATIONS,
            'fetch:stocks' => FetchTypeEnum::STOCKS,
            'fetch:customers' => FetchTypeEnum::CUSTOMERS,
            'fetch:employees' => FetchTypeEnum::EMPLOYEES,
            'fetch:supplier-products' => FetchTypeEnum::SUPPLIER_PRODUCTS,
            default => FetchTypeEnum::BASE,
        };
    }


}
