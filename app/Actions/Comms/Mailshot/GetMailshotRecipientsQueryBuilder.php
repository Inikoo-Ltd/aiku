<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\Filters\FilterByDepartment;
use App\Actions\Comms\Mailshot\Filters\FilterByFamily;
use Illuminate\Support\Arr;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Query;
use App\Models\Comms\Mailshot;
use Spatie\QueryBuilder\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Helpers\Query\WithQueryCompiler;
use App\Actions\Traits\WithCheckCanContactByEmail;
use App\Actions\Comms\Mailshot\Filters\FilterByOrderValue;
use App\Actions\Helpers\Query\GetQueryEloquentQueryBuilder;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersInBasket;
use App\Actions\Comms\Mailshot\Filters\FilterBySubdepartment;
use App\Actions\Comms\Mailshot\Filters\FilterGoldRewardStatus;
use App\Actions\Comms\Mailshot\Filters\FilterByFamilyNeverOrdered;
use App\Actions\Comms\Mailshot\Filters\FilterRegisteredNeverOrdered;
use App\Actions\Comms\Mailshot\Filters\FilterByShowroomOrders;
use App\Actions\Comms\Mailshot\Filters\FilterByInterest;
use App\Actions\Comms\Mailshot\Filters\FilterOrdersCollection;
use App\Actions\Comms\Mailshot\Filters\FilterByLocation;

class GetMailshotRecipientsQueryBuilder
{
    use AsObject;
    use WithCheckCanContactByEmail;
    use WithQueryCompiler;

    /**
     * @throws \Exception
     */
    public function handle(Mailshot $mailshot): ?QueryBuilder
    {

        if (!empty($mailshot->recipients_recipe)) {
            return $this->getRecipientsFromCustomQuery($mailshot);
        }

        return null;
        // return match (Arr::get($mailshot->recipients_recipe, 'recipient_builder_type')) {
        //     'query' => $this->getRecipientsFromQuery($mailshot),
        //     'prospects' => $this->getRecipientsFromProspectsList(Arr::get($mailshot->recipients_recipe, 'recipient_builder_data.prospects')),
        //     'custom_prospects_query' => $this->getRecipientsFromCustomQuery($mailshot),
        //     default => null
        // };
    }

    /**
     * @throws \Exception
     */
    private function getRecipientsFromQuery(Mailshot $mailshot): QueryBuilder
    {
        /** @var Query $query */
        $query = Query::find(Arr::get($mailshot->recipients_recipe, 'recipient_builder_data.query.id'));

        $customArguments = null;
        if ($query->has_arguments) {
            $customArguments = $this->compileConstrains(Arr::get($mailshot->recipients_recipe, 'recipient_builder_data.query.data'));
        }


        return GetQueryEloquentQueryBuilder::run($query, $customArguments);
    }

    private function getRecipientsFromProspectsList(array $prospectIDs): QueryBuilder
    {
        return QueryBuilder::for(Prospect::class)
            ->whereIn('id', $prospectIDs)
            ->where('parent_type', 'Shop')
            ->whereNotNull('email')->where('dont_contact_me', false);
    }

    /**
     * @throws \Exception
     */
    private function getRecipientsFromCustomQuery(Mailshot $mailshot): QueryBuilder
    {
        $modelClass = Customer::class;

        $query = QueryBuilder::for($modelClass);

        // Check if customer is subscribed to marketing
        $query->join('customer_comms', 'customers.id', '=', 'customer_comms.customer_id')
            ->where('customer_comms.is_subscribed_to_marketing', true);

        if ($mailshot->shop_id) {
            $query->where('shop_id', $mailshot->shop_id);
        } else {
            $query->whereRaw('1 = 0');
        }
        $query->whereNotNull('email');

        $filters = $mailshot->recipients_recipe;
        \Log::info('GetMailshotRecipientsQueryBuilder: ' . json_encode($filters));

        // Filter Registered Never Ordered
        (new FilterRegisteredNeverOrdered())->apply($query, $filters);

        // Filter By Family Never Ordered
        (new FilterByFamilyNeverOrdered())->apply($query, $filters);

        // Filter Gold Reward Status
        (new FilterGoldRewardStatus())->apply($query, $filters);

        // Filter Orders In Basket
        (new FilterOrdersInBasket())->apply($query, $filters);

        // FILTER: By Order Value
        (new FilterByOrderValue())->apply($query, $filters);

        // FILTER: By Subdepartment
        (new FilterBySubdepartment())->apply($query, $filters);

        // FILTER: By Department
        (new FilterByDepartment())->apply($query, $filters);

        // FILTER: By Showroom Orders
        (new FilterByShowroomOrders())->apply($query, $filters);

        // FILTER: By Interest
        (new FilterByInterest())->apply($query, $filters);

        // FILTER: By Orders Collection
        (new FilterOrdersCollection())->apply($query, $filters);

        // FILTER: By Family
        (new FilterByFamily())->apply($query, $filters);

        // FILTER: By Location (Radius & Country/Postcode)
        (new FilterByLocation())->apply($query, $filters);

        // NOTE: for debug log the SQL query
        // \Log::info($query->toRawSql());

        return $query;
    }
}
