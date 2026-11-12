<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgreedTermsAuthorisation;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierAgreementVersion;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The versions of the group's Supplier Agreement and how many active suppliers stand where with it. Procurement
 * supervisors start a new version here.
 */
class IndexSupplierAgreementVersions extends OrgAction
{
    use WithAgreedTermsAuthorisation;

    public function handle(Group $group): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierAgreementVersion::class)
            ->where('supplier_agreement_versions.group_id', $group->id)
            ->leftJoin('users', 'users.id', 'supplier_agreement_versions.published_by_user_id')
            ->select([
                'supplier_agreement_versions.id',
                'supplier_agreement_versions.version',
                'supplier_agreement_versions.title',
                'supplier_agreement_versions.state',
                'supplier_agreement_versions.requires_reacceptance',
                'supplier_agreement_versions.change_summary',
                'supplier_agreement_versions.published_at',
                'users.contact_name as published_by',
            ])
            ->selectRaw('(select count(distinct a.supplier_id) from supplier_agreement_acceptances a where a.supplier_agreement_version_id = supplier_agreement_versions.id) as number_acceptances')
            ->defaultSort('-version')
            ->allowedSorts(['version', 'published_at'])
            ->withPaginator(null)
            ->withQueryString();
    }

    /**
     * @return array<string, int>|null
     */
    public function supplierCounts(Group $group): ?array
    {
        $sql = GetSupplierAgreementStatus::make()->statusSql($group->id);
        if ($sql === null) {
            return null;
        }

        $counts = Supplier::where('group_id', $group->id)->where('status', true)
            ->selectRaw("$sql as agreement_status, count(*) as number")
            ->groupByRaw($sql)
            ->toBase()
            ->pluck('number', 'agreement_status');

        return collect(SupplierAgreementStatusEnum::cases())->mapWithKeys(fn (SupplierAgreementStatusEnum $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('version'), __('versions')])
                ->withEmptyState(['title' => __('No version yet'), 'description' => __('Start the first version of the Supplier Agreement.')])
                ->column(key: 'version', label: __('Version'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'title', label: __('Title'), canBeHidden: false)
                ->column(key: 'requires_reacceptance', label: __('Accept again'), canBeHidden: false)
                ->column(key: 'published_at', label: __('Published'), canBeHidden: false, sortable: true)
                ->column(key: 'number_acceptances', label: __('Acceptances'), canBeHidden: false, align: 'right')
                ->defaultSort('-version');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['supply-chain.view', 'compliance.view']) || $this->isProcurementSupervisor($request->user(), $this->group);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(LengthAwarePaginator $versions, ActionRequest $request): Response
    {
        $isSupervisor = $this->isProcurementSupervisor($request->user(), $this->group);
        $draft        = SupplierAgreementVersion::where('group_id', $this->group->id)->where('state', SupplierAgreementVersionStateEnum::DRAFT)->first();

        return Inertia::render('SupplyChain/SupplierAgreementVersions', [
            'breadcrumbs'     => $this->getBreadcrumbs(),
            'title'           => __('Supplier Agreement'),
            'pageHead'        => [
                'title' => __('Supplier Agreement'),
                'icon'  => ['icon' => ['fal', 'fa-file-signature'], 'title' => __('Supplier Agreement')],
            ],
            'supplier_counts' => $this->supplierCounts($this->group),
            'can_edit'        => $isSupervisor,
            'draft_route'     => $draft ? ['name' => 'grp.supply-chain.supplier_agreement.show', 'parameters' => ['supplierAgreementVersion' => $draft->id]] : null,
            'store_route'     => $isSupervisor && !$draft ? ['name' => 'grp.models.supplier_agreement_version.store', 'parameters' => []] : null,
            'data'            => JsonResource::collection($versions->through(fn (SupplierAgreementVersion $version) => [
                'id'                    => $version->id,
                'version'               => $version->version,
                'title'                 => $version->title,
                'state'                 => ['value' => $version->state->value, 'label' => $version->state->label()],
                'requires_reacceptance' => $version->requires_reacceptance,
                'change_summary'        => $version->change_summary,
                'published_at'          => $version->published_at?->toIso8601String(),
                'published_by'          => $version->published_by,
                'number_acceptances'    => (int) $version->number_acceptances,
            ])),
        ])->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Supplier Agreement'),
                    'icon'  => 'fal fa-file-signature',
                    'route' => ['name' => 'grp.supply-chain.supplier_agreement.index'],
                ],
            ]]
        );
    }
}
