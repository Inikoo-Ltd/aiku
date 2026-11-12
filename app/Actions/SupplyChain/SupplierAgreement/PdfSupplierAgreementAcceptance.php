<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\OrgAction;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * The version a supplier accepted, printed with the proof of its acceptance: who, for which company, when, from
 * where, and the hash of the text, which matches the text printed.
 */
class PdfSupplierAgreementAcceptance extends OrgAction
{
    public function handle(SupplierAgreementAcceptance $acceptance): string
    {
        $acceptance->loadMissing(['version', 'supplier', 'shareLink', 'ipGeolocation']);

        return PDF::loadView('procurement.templates.pdf.supplier-agreement-acceptance', [
            'acceptance'  => $acceptance,
            'version'     => $acceptance->version,
            'supplier'    => $acceptance->supplier,
            'bodyHtml'    => $acceptance->version->bodyHtml(),
            'hashMatches' => hash_equals($acceptance->body_hash, $acceptance->version->bodyHash()),
        ])->output();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo('supply-chain.view')) {
            return true;
        }

        $permissions = $this->group->organisations()->where('type', '!=', OrganisationTypeEnum::AGENT)->pluck('id')->map(fn (int $id) => "procurement.$id.view")->all();

        return $permissions !== [] && $request->user()->authTo($permissions);
    }

    public function asController(Supplier $supplier, SupplierAgreementAcceptance $supplierAgreementAcceptance, ActionRequest $request): Response
    {
        abort_unless($supplierAgreementAcceptance->supplier_id === $supplier->id, 404);
        $this->initialisationFromGroup($supplier->group, $request);

        return response($this->handle($supplierAgreementAcceptance), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="supplier-agreement-'.Str::slug($supplier->code).'-v'.$supplierAgreementAcceptance->version->version.'.pdf"');
    }
}
