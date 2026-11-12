<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgreedTermsAuthorisation;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\Models\SupplyChain\SupplierAgreementVersion;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One version of the Supplier Agreement as suppliers read it. A draft is edited and published here by a procurement
 * supervisor; a published one is read only.
 */
class ShowSupplierAgreementVersion extends OrgAction
{
    use WithAgreedTermsAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['supply-chain.view', 'compliance.view']) || $this->isProcurementSupervisor($request->user(), $this->group);
    }

    public function asController(SupplierAgreementVersion $supplierAgreementVersion, ActionRequest $request): SupplierAgreementVersion
    {
        $this->initialisationFromGroup($supplierAgreementVersion->group, $request);

        return $supplierAgreementVersion;
    }

    public function htmlResponse(SupplierAgreementVersion $version, ActionRequest $request): Response
    {
        $isDraft  = $version->state === SupplierAgreementVersionStateEnum::DRAFT;
        $canEdit  = $isDraft && $this->isProcurementSupervisor($request->user(), $this->group);
        $isFirst  = !SupplierAgreementVersion::where('group_id', $version->group_id)->released()->exists();
        $title    = __('Supplier Agreement v:version', ['version' => $version->version]);
        $version->loadMissing('publishedBy');

        return Inertia::render('SupplyChain/SupplierAgreementVersion', [
            'breadcrumbs' => array_merge(IndexSupplierAgreementVersions::make()->getBreadcrumbs(), [[
                'type'   => 'simple',
                'simple' => [
                    'label' => 'v'.$version->version,
                    'route' => ['name' => 'grp.supply-chain.supplier_agreement.show', 'parameters' => ['supplierAgreementVersion' => $version->id]],
                ],
            ]]),
            'title'       => $title,
            'pageHead'    => [
                'title' => $title,
                'model' => $version->state->label(),
                'icon'  => ['icon' => ['fal', 'fa-file-signature'], 'title' => __('Supplier Agreement')],
            ],
            'version'     => [
                'id'                    => $version->id,
                'version'               => $version->version,
                'title'                 => $version->title,
                'body'                  => $version->body,
                'body_html'             => $version->bodyHtml(),
                'change_summary'        => $version->change_summary,
                'requires_reacceptance' => $version->requires_reacceptance,
                'state'                 => ['value' => $version->state->value, 'label' => $version->state->label()],
                'published_at'          => $version->published_at?->toIso8601String(),
                'published_by'          => $version->publishedBy?->contact_name,
                'body_hash'             => $isDraft ? null : $version->bodyHash(),
                'number_acceptances'    => $version->acceptances()->distinct()->count('supplier_id'),
            ],
            'is_first'    => $isFirst,
            'can_edit'    => $canEdit,
            'routes'      => $canEdit ? [
                'update'  => ['name' => 'grp.models.supplier_agreement_version.update', 'parameters' => ['supplierAgreementVersion' => $version->id]],
                'publish' => ['name' => 'grp.models.supplier_agreement_version.publish', 'parameters' => ['supplierAgreementVersion' => $version->id]],
            ] : null,
        ]);
    }
}
