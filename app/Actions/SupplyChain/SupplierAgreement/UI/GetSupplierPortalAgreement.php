<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement\UI;

use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use App\Models\SupplyChain\SupplierAgreementVersion;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the Agreement tab of the supplier portal shows: the version in force, where the supplier stands with it,
 * what changed since the version it accepted when no new acceptance is needed, and where the form is sent.
 */
class GetSupplierPortalAgreement
{
    use AsObject;

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Supplier $supplier, string $acceptRoute): ?array
    {
        $statuses = GetSupplierAgreementStatus::make();
        $current  = $statuses->currentVersion($supplier->group_id);
        if (!$current) {
            return null;
        }

        /** @var SupplierAgreementAcceptance|null $acceptance */
        $acceptance = $statuses->latestAcceptances([$supplier->id])->get($supplier->id);
        $status     = $statuses->statusOf($acceptance?->version?->version, $statuses->requiredVersionNumber($supplier->group_id));

        return [
            'version'         => [
                'id'             => $current->id,
                'version'        => $current->version,
                'title'          => $current->title,
                'body_html'      => $current->bodyHtml(),
                'published_at'   => $current->published_at?->toIso8601String(),
            ],
            'status'          => $statuses->present($status, $acceptance, $current),
            'must_accept'     => $status !== SupplierAgreementStatusEnum::ACCEPTED,
            'last_acceptance' => $acceptance ? [
                'version'             => $acceptance->version->version,
                'accepted_at'         => $acceptance->accepted_at->toIso8601String(),
                'supplier_legal_name' => $acceptance->supplier_legal_name,
                'authorised_person'   => $acceptance->authorised_person,
                'position'            => $acceptance->position,
            ] : null,
            'changes'         => $acceptance && $status === SupplierAgreementStatusEnum::ACCEPTED
                ? SupplierAgreementVersion::where('group_id', $supplier->group_id)->released()
                    ->where('version', '>', $acceptance->version->version)
                    ->orderBy('version')
                    ->get(['version', 'change_summary', 'published_at'])
                    ->map(fn (SupplierAgreementVersion $version) => [
                        'version'        => $version->version,
                        'change_summary' => $version->change_summary,
                        'published_at'   => $version->published_at?->toIso8601String(),
                    ])->all()
                : [],
            'legal_name'      => $acceptance?->supplier_legal_name ?? $supplier->officialName(),
            'accept_route'    => $acceptRoute,
        ];
    }
}
