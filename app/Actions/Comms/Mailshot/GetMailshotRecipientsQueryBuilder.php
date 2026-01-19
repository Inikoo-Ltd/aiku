<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use Illuminate\Support\Arr;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Query;
use App\Models\Comms\Mailshot;
use Spatie\QueryBuilder\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Helpers\Query\WithQueryCompiler;
use App\Actions\Traits\WithCheckCanContactByEmail;
use App\Actions\Helpers\Query\GetQueryEloquentQueryBuilder;
use App\Actions\Comms\Mailshot\Filters\FilterRegisteredNeverOrdered;

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

        $regNeverOrdered = Arr::get($filters, 'registered_never_ordered');
        $isRegNeverOrderedActive = is_array($regNeverOrdered) ? ($regNeverOrdered['value'] ?? false) : $regNeverOrdered;

        if ($isRegNeverOrderedActive) {
            $options = [];

            if (is_array($regNeverOrdered) && isset($regNeverOrdered['value'])) {
                $val = $regNeverOrdered['value'];

                if (is_array($val) && isset($val['date_range'])) {
                    $rawDateRange = $val['date_range'];

                    if (is_array($rawDateRange) && count($rawDateRange) >= 2) {
                        $options['date_range'] = [
                            'start' => $rawDateRange[0],
                            'end'   => $rawDateRange[1]
                        ];
                    }
                }
            }

            (new FilterRegisteredNeverOrdered())->apply($query, $options);
        }

        $familyFilter = Arr::get($filters, 'by_family_never_ordered');
        $familyId = is_array($familyFilter) ? ($familyFilter['value'] ?? null) : $familyFilter;
        if ($familyId) {
            $query->whereDoesntHave('orders.items.product', function ($q) use ($familyId) {
                $q->where('category_id', $familyId);
            });
        }

        return $query;
    }

}
