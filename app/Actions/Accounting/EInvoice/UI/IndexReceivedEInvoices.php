<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\GetSupplierOptionFields;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Enums\Accounting\ReceivedEInvoice\ReceivedEInvoiceMatchStateEnum;
use App\Http\Resources\Accounting\ReceivedEInvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\ReceivedEInvoice;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * E-invoices suppliers sent to the organisation over Peppol, newest first, with the XML and PDF as received and the
 * supplier, order and delivery staff matched them to.
 */
class IndexReceivedEInvoices extends OrgAction
{
    use WithAccountingAuthorisation;

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $search = AllowedFilter::callback('global', function (Builder $query, mixed $value) {
            $value = trim((string)$value);
            $query->where(fn (Builder $query) => $query
                ->whereLike('received_e_invoices.document_number', "%$value%")
                ->orWhereLike('received_e_invoices.sender_name', "%$value%")
                ->orWhereLike('received_e_invoices.supplier_name', "%$value%")
                ->orWhereLike('received_e_invoices.order_reference', "%$value%"));
        });

        $query = ReceivedEInvoice::query()
            ->where('received_e_invoices.organisation_id', $organisation->id)
            ->leftJoin('suppliers', 'suppliers.id', '=', 'received_e_invoices.supplier_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'received_e_invoices.purchase_order_id')
            ->leftJoin('stock_deliveries', 'stock_deliveries.id', '=', 'received_e_invoices.stock_delivery_id')
            ->select([
                'received_e_invoices.id',
                'received_e_invoices.received_at',
                'received_e_invoices.sender_name',
                'received_e_invoices.sender_peppol_id',
                'received_e_invoices.supplier_name',
                'received_e_invoices.document_type',
                'received_e_invoices.document_number',
                'received_e_invoices.issue_date',
                'received_e_invoices.due_date',
                'received_e_invoices.currency_code',
                'received_e_invoices.total_amount',
                'received_e_invoices.order_reference',
                'received_e_invoices.parse_error',
                'received_e_invoices.xml_sha256',
                'received_e_invoices.pdf_sha256',
                'received_e_invoices.acknowledged_at',
                'received_e_invoices.match_state',
                'received_e_invoices.match_data',
                'received_e_invoices.supplier_id',
                'suppliers.code as matched_supplier_code',
                'suppliers.name as matched_supplier_name',
                'purchase_orders.reference as purchase_order_reference',
                'stock_deliveries.reference as stock_delivery_reference',
            ]);

        return QueryBuilder::for($query)
            ->defaultSort('-received_at')
            ->allowedSorts(['received_at', 'document_number', 'issue_date', 'due_date', 'total_amount', 'sender_name'])
            ->allowedFilters([$search, AllowedFilter::exact('match_state', 'received_e_invoices.match_state')])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('e-invoice'), __('e-invoices')])
                ->withEmptyState(['title' => __('No e-invoices received yet')])
                ->column(key: 'received_at', label: __('Received'), sortable: true)
                ->column(key: 'sender_name', label: __('From'), sortable: true)
                ->column(key: 'document_number', label: __('Number'), sortable: true)
                ->column(key: 'issue_date', label: __('Issued'), sortable: true)
                ->column(key: 'due_date', label: __('Due'), sortable: true)
                ->column(key: 'total_amount', label: __('Total'), sortable: true, align: 'right')
                ->column(key: 'match', label: __('Supplier / order'))
                ->column(key: 'files', label: __('Files'))
                ->column(key: 'actions', label: '');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $rows, ActionRequest $request): Response
    {
        $title = __('E-invoices received');

        return Inertia::render(
            'Org/Accounting/ReceivedEInvoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => ['fal', 'fa-inbox-in'],
                    'title' => $title,
                ],
                'canEdit'          => $this->canEdit,
                'organisationId'   => $this->organisation->id,
                'matchStates'      => ReceivedEInvoiceMatchStateEnum::labels(),
                'suppliers'        => Inertia::once(fn () => $this->supplierOptions()),
                'data'             => ReceivedEInvoicesResource::collection($rows),
            ]
        )->table($this->tableStructure());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supplierOptions(): array
    {
        $suppliers = Supplier::query()
            ->whereIn('id', fn ($query) => $query->select('supplier_id')->from('org_suppliers')->where('organisation_id', $this->organisation->id))
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'company_name', 'contact_name']);
        $optionFields = GetSupplierOptionFields::make()->handle($suppliers);

        return $suppliers
            ->map(fn (Supplier $supplier) => [
                'id'   => $supplier->id,
                'name' => $supplier->name,
                ...$optionFields->get($supplier->id),
            ])
            ->all();
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => $routeName, 'parameters' => $routeParameters],
                        'label' => __('E-invoices received'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
