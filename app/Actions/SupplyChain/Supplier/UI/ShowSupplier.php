<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:15:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\Procurement\SupplierAgreedTerm\UI\IndexSupplierAgreedTerms;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\DeactivateSupplier;
use App\Actions\SupplyChain\Supplier\ReactivateSupplier;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Http\Resources\Procurement\SupplierMessagesResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplier extends OrgAction
{
    use WithBucketNavigation;
    use WithSupplierSubNavigation;
    use WithSupplyChainAuthorisation;

    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($supplier->group, $request);
        $this->redirectRetiredDetailsTab()->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($supplier->group, $request);
        $this->redirectRetiredDetailsTab()->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    private function redirectRetiredDetailsTab(): static
    {
        if ($this->get('tab') === 'details') {
            $this->set('tab', SupplierTabsEnum::SHOWCASE->value);
        }

        return $this;
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {
        $editRoute = $this->canEdit ? [
            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
            'parameters' => $request->route()->originalParameters(),
        ] : null;

        return Inertia::render(
            'SupplyChain/Supplier',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next'     => $this->getNext($supplier, $request),
                ],
                'title'       => __('Supplier'),
                'pageHead'    => [
                    'title'         => $supplier->name,
                    'icon'          => [
                        'icon'  => 'fal fa-person-dolly',
                        'title' => __('Supplier'),
                    ],
                    'model'         => __('Supplier'),
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => $editRoute['name'],
                                'parameters' => array_values($editRoute['parameters'])
                            ]
                        ] : false,
                    ],
                    'subNavigation' => $this->getSupplierNavigation($supplier),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation(),
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierShowcase::run($supplier, $editRoute)
                    : Inertia::optional(fn () => GetSupplierShowcase::run($supplier, $editRoute)),

                'productDevelopments' => (new ProductDevelopmentAccess())->canSeeAny($request->user())

                    ? ['rows' => GetRelatedProductDevelopments::make()->forSupplier($supplier, $request->user())]

                    : null,

                'archive' => [
                    'is_archived'   => !$supplier->status,
                    'supplier_name' => $supplier->name,
                    'archived_at'   => $supplier->archived_at,
                    'can_archive'   => DeactivateSupplier::canArchive($request->user(), $supplier) && !ReactivateSupplier::wasMerged($supplier),
                    'route'         => [
                        'name'       => $supplier->status ? 'grp.models.supplier.archive' : 'grp.models.supplier.unarchive',
                        'parameters' => ['supplier' => $supplier->id],
                        'method'     => 'patch',
                    ],
                ],

                'agreement' => GetAgreement::run($supplier, null, $request->user())
                    + ['print_route' => ['name' => 'grp.supply-chain.suppliers.agreement.pdf', 'parameters' => [$supplier->slug]]],

                SupplierTabsEnum::AGREED_TERMS->value => $this->tab == SupplierTabsEnum::AGREED_TERMS->value ?
                    fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($supplier, SupplierTabsEnum::AGREED_TERMS->value))
                    : Inertia::optional(fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($supplier, SupplierTabsEnum::AGREED_TERMS->value))),

                SupplierTabsEnum::NOTES->value => $this->tab == SupplierTabsEnum::NOTES->value ?
                    fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($supplier, SupplierTabsEnum::NOTES->value))
                    : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($supplier, SupplierTabsEnum::NOTES->value))),

                SupplierTabsEnum::INBOX->value => $this->tab == SupplierTabsEnum::INBOX->value ?
                    fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($supplier, SupplierTabsEnum::INBOX->value))
                    : Inertia::optional(fn () => SupplierMessagesResource::collection(IndexSupplierMessages::run($supplier, SupplierTabsEnum::INBOX->value))),

                SupplierTabsEnum::DECLARATIONS->value => $this->tab == SupplierTabsEnum::DECLARATIONS->value ?
                    fn () => GetSupplierDeclarations::run($supplier)
                    : Inertia::optional(fn () => GetSupplierDeclarations::run($supplier)),

                SupplierTabsEnum::ATTACHMENTS->value => $this->tab == SupplierTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($supplier, SupplierTabsEnum::ATTACHMENTS->value))
                    : Inertia::optional(fn () => AttachmentsResource::collection(IndexAttachments::run($supplier, SupplierTabsEnum::ATTACHMENTS->value))),

                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.supplier.attachment.attach',
                        'parameters' => ['supplier' => $supplier->id],
                    ],
                    'detachRoute' => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.supplier.attachment.detach',
                        'parameters' => ['supplier' => $supplier->id],
                    ],
                ],
                'attachmentScopes' => SupplyChainAttachmentScopeEnum::options(),

                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplier, SupplierTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($supplier, SupplierTabsEnum::HISTORY->value))),
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value))
            ->table(IndexSupplierAgreedTerms::make()->tableStructure(prefix: SupplierTabsEnum::AGREED_TERMS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: SupplierTabsEnum::NOTES->value))
            ->table(IndexSupplierMessages::make()->tableStructure($supplier, prefix: SupplierTabsEnum::INBOX->value))
            ->table(IndexAttachments::make()->tableStructure(prefix: SupplierTabsEnum::ATTACHMENTS->value));
    }

    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Supplier $supplier, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Suppliers'),
                            'route' => $routeParameters['index'],
                        ],
                        'model' => [
                            'label' => $supplier->code,
                            'route' => $routeParameters['model'],
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.index',
            'grp.supply-chain.suppliers.show' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.suppliers.index',
                            'parameters' => [],
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.suppliers.show',
                            'parameters' => [$supplier->slug],
                        ],
                    ],
                    $suffix,
                ),
            ),
            'grp.supply-chain.agents.show.suppliers.show' =>
            array_merge(
                ShowAgent::make()->getBreadcrumbs($supplier->agent, $routeName, $routeParameters),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.index',
                            'parameters' => Arr::only($routeParameters, 'agent'),
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix,
                ),
            ),
            default => [],
        };
    }

    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierNeighbour($supplier, $request, forward: false), $request);
    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierNeighbour($supplier, $request, forward: true), $request);
    }

    private function getSupplierNeighbour(Supplier $supplier, ActionRequest $request, bool $forward): ?Supplier
    {
        $query = Supplier::query()->where('suppliers.group_id', $supplier->group_id);

        if (array_key_exists('agent', $request->route()->originalParameters())) {
            $query->where('suppliers.agent_id', $supplier->agent_id);
        } elseif ($request->input('bucket') == 'free') {
            $query->whereNull('suppliers.agent_id');
        } elseif ($request->input('bucket') == 'in_agents') {
            $query->whereNotNull('suppliers.agent_id');
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $supplier,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code' => 'suppliers.code',
                'name' => 'suppliers.name',
            ],
            defaultSort: ['suppliers.code', false],
            forward: $forward,
        );
    }

    private function getNavigation(?Supplier $supplier, ActionRequest $request): ?array
    {
        if (!$supplier) {
            return null;
        }

        return [
            'label' => $supplier->code,
            'route' => [
                'name'       => $request->route()->getName(),
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    ['supplier' => $supplier->slug]
                ),
            ],
        ];
    }

}
