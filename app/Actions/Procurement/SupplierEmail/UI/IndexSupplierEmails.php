<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierEmail\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Http\Resources\Procurement\SupplierEmailsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierEmail;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSupplierEmails extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation|OrgSupplier|Supplier $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAny(['supplier_emails.subject', 'supplier_emails.from_address', 'supplier_emails.from_name', 'suppliers.name'], 'ILIKE', "%$value%");
            });
        });

        $query = QueryBuilder::for(SupplierEmail::class)
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_emails.supplier_id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'supplier_emails.org_supplier_id')
            ->join('organisations', 'organisations.id', 'supplier_emails.organisation_id');

        match (true) {
            $parent instanceof Organisation => $query->where('supplier_emails.organisation_id', $parent->id),
            $parent instanceof OrgSupplier => $query->where('supplier_emails.org_supplier_id', $parent->id),
            $parent instanceof Supplier => $query->where('supplier_emails.supplier_id', $parent->id),
        };

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $query
            ->select([
                'supplier_emails.id',
                'supplier_emails.direction',
                'supplier_emails.from_address',
                'supplier_emails.from_name',
                'supplier_emails.to',
                'supplier_emails.subject',
                'supplier_emails.snippet',
                'supplier_emails.attachments',
                'supplier_emails.sent_at',
                'suppliers.name as supplier_name',
                'org_suppliers.slug as org_supplier_slug',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
            ])
            ->defaultSort('-sent_at')
            ->allowedSorts(['sent_at', 'subject', 'supplier_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    protected function getElementGroups(Organisation|OrgSupplier|Supplier $parent): array
    {
        $groups = [
            'direction' => [
                'label'    => __('Direction'),
                'elements' => [
                    SupplierEmailDirectionEnum::INBOUND->value  => [__('Received'), null],
                    SupplierEmailDirectionEnum::OUTBOUND->value => [__('Sent'), null],
                ],
                'engine'   => fn ($query, $elements) => $query->whereIn('supplier_emails.direction', $elements),
            ],
        ];

        if ($parent instanceof Organisation) {
            $groups['routing'] = [
                'label'    => __('Supplier'),
                'elements' => [
                    'assigned'   => [__('Assigned'), null],
                    'unassigned' => [__('Unassigned'), null],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        in_array('unassigned', $elements)
                            ? $query->whereNull('supplier_emails.org_supplier_id')
                            : $query->whereNotNull('supplier_emails.org_supplier_id');
                    }
                },
            ];
        }

        return $groups;
    }

    public function tableStructure(Organisation|OrgSupplier|Supplier $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No emails yet'),
                    'description' => $parent instanceof Organisation && ! Arr::get($parent->settings, 'procurement.gmail.email')
                        ? __('Connect the procurement mailbox in Procurement settings to start receiving supplier emails here.')
                        : null,
                ])
                ->column(key: 'direction', label: '', canBeHidden: false)
                ->column(key: 'sent_at', label: __('Date'), canBeHidden: false, sortable: true);

            if (! $parent instanceof OrgSupplier) {
                $table->column(key: 'supplier_name', label: __('Supplier'), canBeHidden: false, sortable: true);
            }

            if ($parent instanceof Supplier) {
                $table->column(key: 'organisation_code', label: __('Organisation'), canBeHidden: false);
            }

            $table
                ->column(key: 'correspondent', label: __('From / To'), canBeHidden: false)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $emails, ActionRequest $request): Response
    {
        $title = __('Supplier emails');

        return Inertia::render(
            'Procurement/SupplierEmails',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'    => $title,
                    'icon'     => ['fal', 'fa-inbox'],
                ],
                'mailbox'     => Arr::get($this->organisation->settings, 'procurement.gmail.email'),
                'data'        => SupplierEmailsResource::collection($emails),
            ]
        )->table($this->tableStructure($this->organisation));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.supplier_emails.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Emails'),
                    ],
                ],
            ]
        );
    }
}
