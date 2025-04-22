<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:12:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInOrganisationSalesResource;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexInvoiceCategoriesSalesTable extends OrgAction
{
    public function handle(Group|Organisation $parent)
    {
        $queryBuilder = QueryBuilder::for(InvoiceCategory::class);
        $queryBuilder->leftJoin('invoice_category_sales_intervals', 'invoice_categories.id', 'invoice_category_sales_intervals.invoice_category_id');
        $queryBuilder->leftJoin('invoice_category_ordering_intervals', 'invoice_categories.id', 'invoice_category_ordering_intervals.invoice_category_id');
        $queryBuilder->leftJoin('organisations', 'invoice_categories.organisation_id', 'organisations.id');


        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('invoice_categories.organisation_id', $parent->id);
        } else {
            $queryBuilder->where('invoice_categories.group_id', $parent->id);
        }


        $queryBuilder
            ->defaultSort('invoice_categories.name')
            ->select([
                'invoice_categories.id',
                'invoice_categories.name',
                'invoice_categories.slug',
                'invoice_categories.state',
                'invoice_categories.currency_id as category_currency_id',
                'organisations.currency_id as organisation_currency_id',
                'invoice_categories.organisation_id',
                'invoice_category_sales_intervals.*',
                'invoice_category_ordering_intervals.*',
            ]);

        if ($parent instanceof Group) {
            $queryBuilder->selectRaw('\''.$parent->currency->code.'\' as group_currency_code');
        } else {
            $queryBuilder->selectRaw('\''.$parent->group->currency->code.'\' as group_currency_code');
        }

        return $queryBuilder->allowedSorts(['name', 'state'])
            ->withPaginator(null)
            ->withQueryString();
    }


    public function action(Group|Organisation $parent): array
    {
        if ($parent instanceof Group) {
            $this->initialisationFromGroup($parent, []);
        } else {
            $this->initialisation($parent, []);
        }
        $shops = $this->handle($parent);

        if ($parent instanceof Group) {
            return json_decode(DashboardInvoiceCategoriesInGroupSalesResource::collection($shops)->toJson(), true);
        } else {
            return json_decode(DashboardInvoiceCategoriesInOrganisationSalesResource::collection($shops)->toJson(), true);
        }
    }

}
