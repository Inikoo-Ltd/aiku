<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexSupplierCoverBucketItems extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgSupplierSubNavigation;

    private OrgSupplier $orgSupplier;
    private string $bucket;
    private ?string $rank;

    public function handle(OrgSupplier $orgSupplier, string $bucket, ?string $rank): LengthAwarePaginator
    {
        $ids = GetSupplierStockCoverBuckets::make()->orgSupplierProductIdsInBucket($orgSupplier, $bucket, $rank);

        $paginator = DB::table('org_supplier_products as p')
            ->join('supplier_products as sp', 'sp.id', 'p.supplier_product_id')
            ->leftJoin('org_stock_has_org_supplier_products as link', function ($join) {
                $join->on('link.org_supplier_product_id', 'p.id')->where('link.status', true);
            })
            ->leftJoin('org_stocks as os', 'os.id', 'link.org_stock_id')
            ->leftJoin('org_stock_stats as s', 's.org_stock_id', 'os.id')
            ->leftJoin('shopping_list_items as sli', function ($join) {
                $join->on('sli.org_supplier_product_id', 'p.id')
                    ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
                    ->whereNull('sli.deleted_at');
            })
            ->whereIn('p.id', $ids)
            ->select([
                'p.id',
                'p.slug',
                'sp.code',
                'sp.name',
                'sp.cost',
                'sp.units_per_carton',
                'sp.measured_lead_time_days',
                'sp.estimated_lead_time_days',
                'os.quantity_available as our_stock',
                's.days_of_cover',
                's.recommended_order_quantity',
                'sli.id as shopping_list_item_id',
                'sli.quantity_units as ordered_quantity',
            ])
            ->when(
                $ids,
                fn ($query) => $query->orderByRaw(
                    'array_position(array['.implode(',', array_map(intval(...), $ids)).']::bigint[], p.id)'
                )
            )
            ->paginate(25)
            ->withQueryString();

        $paginator->getCollection()->transform(fn ($row) => [
            'id'                    => $row->id,
            'slug'                  => $row->slug,
            'code'                  => $row->code,
            'name'                  => $row->name,
            'cost'                  => $row->cost !== null ? (float) $row->cost : null,
            'units_per_carton'      => $row->units_per_carton !== null ? (int) $row->units_per_carton : null,
            'lead_time_days'        => $row->measured_lead_time_days !== null
                ? (int) $row->measured_lead_time_days
                : ($row->estimated_lead_time_days !== null ? (int) $row->estimated_lead_time_days : null),
            'lead_time_measured'    => $row->measured_lead_time_days !== null,
            'our_stock'             => $row->our_stock !== null ? (float) $row->our_stock : null,
            'our_days_of_cover'     => $row->days_of_cover !== null ? (int) $row->days_of_cover : null,
            'recommended_quantity'  => $row->recommended_order_quantity !== null
                ? (int) ceil((float) $row->recommended_order_quantity)
                : null,
            'shopping_list_item_id' => $row->shopping_list_item_id,
            'ordered_quantity'      => (float) ($row->ordered_quantity ?? 0),
        ]);

        return $paginator;
    }

    private function bucketLabel(): string
    {
        return GetSupplierStockCoverBuckets::make()->bucketLabel(
            $this->bucket,
            GetSupplierLeadTime::run($this->orgSupplier)['days']
        );
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        abort_if($orgSupplier->org_agent_id, 404);

        $this->orgSupplier = $orgSupplier;
        $cover             = $request->input('cover', 'out');
        $this->bucket      = array_key_exists($cover, GetSupplierStockCoverBuckets::BUCKETS) ? $cover : 'out';
        $rank              = $request->input('rank');
        $this->rank        = in_array($rank, ['A', 'B', 'C', 'D', 'Z'], true) ? $rank : null;

        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier, $this->bucket, $this->rank);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/SupplierCoverBucketItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgSupplier, $request->route()->originalParameters()),
                'title'       => $this->bucketLabel(),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => $this->bucketLabel(),
                    ],
                    'model'         => $this->orgSupplier->supplier->name,
                    'title'         => $this->bucketLabel().($this->rank ? ' · '.$this->rank : ''),
                    'subNavigation' => $this->getOrgSupplierNavigation($this->orgSupplier),
                ],
                'orgSupplier' => [
                    'id'       => $this->orgSupplier->id,
                    'slug'     => $this->orgSupplier->slug,
                    'currency' => $this->orgSupplier->supplier->currency->code,
                ],
                'bucket'      => $this->bucket,
                'bucketLabel' => $this->bucketLabel(),
                'rank'        => $this->rank,
                'items'       => $items,
            ]
        );
    }

    public function getBreadcrumbs(OrgSupplier $orgSupplier, array $routeParameters): array
    {
        return array_merge(
            ShowSupplierShoppingDashboard::make()->getBreadcrumbs($orgSupplier, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show.shopping.items.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->bucketLabel(),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
