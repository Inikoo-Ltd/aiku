<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

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
        if (Arr::has($mailshot->recipients_recipe, 'customer_query')) {
            return $this->getRecipientsFromCustomQuery($mailshot);
        }
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


        if ($mailshot->shop_id) {
            $query->where('shop_id', $mailshot->shop_id);
        } else {
            $query->whereRaw('1 = 0');
        }
        $query->whereNotNull('email');
        $filters = Arr::get($mailshot->recipients_recipe, 'customer_query', []);

        // Filter Registered Never Ordered
        (new FilterRegisteredNeverOrdered())->apply($query, $filters);

        // Filter By Family Never Ordered
        (new FilterByFamilyNeverOrdered())->apply($query, $filters);

        // Filter Gold Reward Status
        $goldFilter = Arr::get($filters, 'gold_reward_status');
        $goldStatus = is_array($goldFilter) ? ($goldFilter['value'] ?? null) : $goldFilter;

        if ($goldStatus) {
            (new FilterGoldRewardStatus())->apply($query, $goldStatus);
        }

        // Filter Orders In Basket
        $basketFilter = Arr::get($filters, 'orders_in_basket');
        $isBasketActive = is_array($basketFilter) ? ($basketFilter['value'] ?? false) : $basketFilter;

        if ($isBasketActive) {
            $options = [];

            if (is_array($basketFilter) && isset($basketFilter['value'])) {
                $val = $basketFilter['value'];

                if (isset($val['date_range']) && is_array($val['date_range']) && count($val['date_range']) >= 2) {
                    $options['date_range'] = [
                        'start' => $val['date_range'][0],
                        'end'   => $val['date_range'][1]
                    ];
                }

                if (isset($val['amount_range']) && is_array($val['amount_range'])) {
                    $options['amount_range'] = [
                        'min' => $val['amount_range']['min'] ?? null,
                        'max' => $val['amount_range']['max'] ?? null,
                    ];
                }
            }

            (new FilterOrdersInBasket())->apply($query, $options);
        }

        // FILTER: By Order Value
        $orderValueFilter = Arr::get($filters, 'by_order_value');
        $isOrderValueActive = is_array($orderValueFilter) ? ($orderValueFilter['value'] ?? false) : $orderValueFilter;

        if ($isOrderValueActive) {
            $options = [];

            if (is_array($orderValueFilter) && isset($orderValueFilter['value'])) {
                $val = $orderValueFilter['value'];

                if (isset($val['amount_range']) && is_array($val['amount_range'])) {
                    $options['min'] = $val['amount_range']['min'] ?? null;
                    $options['max'] = $val['amount_range']['max'] ?? null;
                }
            }

            (new FilterByOrderValue())->apply($query, $options);
        }

        // FILTER: By Subdepartment
        $subDeptFilter = Arr::get($filters, 'by_subdepartment');
        if ($subDeptFilter && !empty($subDeptFilter['value'])) {
            $rawValue = $subDeptFilter['value'];
            $valueToSend = [
                'ids' => [],
                'behaviors' => ['purchased']
            ];

            if (is_array($rawValue)) {

                if (array_key_exists('ids', $rawValue)) {
                    $valueToSend['ids'] = $rawValue['ids'] ?? [];

                    if (isset($rawValue['behaviors']) && is_array($rawValue['behaviors'])) {
                        $valueToSend['behaviors'] = $rawValue['behaviors'];
                    }
                } elseif (array_key_exists(0, $rawValue)) {
                    $valueToSend['ids'] = $rawValue;
                } elseif (isset($rawValue['behaviors'])) {
                    $valueToSend['behaviors'] = $rawValue['behaviors'];
                }
            } else {

                $valueToSend['ids'] = [$rawValue];
            }

            if (!empty($valueToSend['ids'])) {
                (new FilterBySubdepartment())->apply($query, $valueToSend);
            }
        }
        // FILTER: By Showroom Orders
        $showroomFilter = Arr::get($filters, 'by_showroom_orders');
        $isShowroomActive = is_array($showroomFilter) ? ($showroomFilter['value'] ?? false) : $showroomFilter;

        if ($isShowroomActive) {
            (new FilterByShowroomOrders())->apply($query);
        }

        // FILTER: By Interest
        $interestFilter = Arr::get($filters, 'by_interest');
        $interestTags = is_array($interestFilter) ? ($interestFilter['value'] ?? []) : [];

        if (!is_array($interestTags) && !is_null($interestTags)) {
            $interestTags = [$interestTags];
        }

        if (!empty($interestTags)) {
            (new FilterByInterest())->apply($query, $interestTags);
        }

        // FILTER: By Orders Collection
        $collectionFilter = Arr::get($filters, 'orders_collection');
        $isCollectionActive = is_array($collectionFilter) ? ($collectionFilter['value'] ?? false) : $collectionFilter;

        if ($isCollectionActive) {
            (new FilterOrdersCollection())->apply($query);
        }

        // FILTER: By Family
        (new FilterByFamily())->apply($query, $filters);

        return $query;
    }
}
