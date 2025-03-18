<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:12:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\OrgAction;
use App\Http\Resources\Dashboards\DashboardInvoiceCategoriesSalesResource;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;

class IndexInvoiceCategoriesSalesTable extends OrgAction
{
    public function handle(Group|Organisation $parent)
    {
        $queryBuilder = QueryBuilder::for(InvoiceCategory::class);
        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('organisation_id', $parent->id);
        } else {
            $queryBuilder->where('group_id', $parent->id);
        }


        return $queryBuilder
            ->defaultSort('invoice_categories.name')
            ->select(['id', 'name', 'slug', 'state', 'invoice_categories.currency_id', 'invoice_categories.organisation_id'])
            ->allowedSorts(['name', 'state'])
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

        return json_decode(DashboardInvoiceCategoriesSalesResource::collection($shops)->toJson(), true);
    }

}
