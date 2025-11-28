<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:12:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\OrgAction;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInGroupSalesResource;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesInOrganisationSalesResource;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\CustomRangeDataService;
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

        $queryBuilder->whereIn('invoice_categories.state', [InvoiceCategoryStateEnum::ACTIVE, InvoiceCategoryStateEnum::COOLDOWN]);

        $queryBuilder
            ->defaultSort('invoice_categories.name')
            ->select([
                'invoice_categories.id',
                'invoice_categories.name',
                'invoice_categories.slug',
                'invoice_categories.state',
                'invoice_categories.colour',
                'invoice_categories.currency_id as category_currency_id',
                'organisations.currency_id as organisation_currency_id',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
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
            ->withPaginator(null, 1000)
            ->withQueryString();
    }

    public function action(Group|Organisation $parent, array $customRangeData = []): array
    {
        if ($parent instanceof Group) {
            $this->initialisationFromGroup($parent, []);
        } else {
            $this->initialisation($parent, []);
        }

        $invoiceCategories = $this->handle($parent);

        // Inject custom range data if available
        if (!empty($customRangeData) && !empty($customRangeData['invoice_categories'])) {
            $customRangeService = app(CustomRangeDataService::class);
            $invoiceCategories->setCollection(
                $customRangeService->injectCustomRangeData($invoiceCategories->getCollection(), $customRangeData, 'invoice_categories')
            );
        }

        if ($parent instanceof Group) {
            return json_decode(DashboardInvoiceCategoriesInGroupSalesResource::collection($invoiceCategories)->toJson(), true);
        } else {
            return json_decode(DashboardInvoiceCategoriesInOrganisationSalesResource::collection($invoiceCategories)->toJson(), true);
        }
    }
}
