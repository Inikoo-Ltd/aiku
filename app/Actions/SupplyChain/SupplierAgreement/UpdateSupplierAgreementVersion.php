<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\Models\SupplyChain\SupplierAgreementVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Only a draft changes. A published or retired version keeps the text suppliers accepted.
 */
class UpdateSupplierAgreementVersion extends OrgAction
{
    use WithSupplierAgreementAuthorisation;

    /**
     * @param  array{title?: string, body?: string, change_summary?: string|null, requires_reacceptance?: bool}  $modelData
     */
    public function handle(SupplierAgreementVersion $version, array $modelData): SupplierAgreementVersion
    {
        if ($version->state !== SupplierAgreementVersionStateEnum::DRAFT) {
            throw ValidationException::withMessages(['version' => __('A published version cannot be changed. Make a new version instead.')]);
        }

        $version->update($modelData);

        return $version;
    }

    public function rules(): array
    {
        return [
            'title'                 => ['sometimes', 'string', 'max:255'],
            'body'                  => ['sometimes', 'string', 'max:100000'],
            'change_summary'        => ['sometimes', 'nullable', 'string', 'max:5000'],
            'requires_reacceptance' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(SupplierAgreementVersion $supplierAgreementVersion, ActionRequest $request): SupplierAgreementVersion
    {
        $this->initialisationFromGroup($supplierAgreementVersion->group, $request);

        return $this->handle($supplierAgreementVersion, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function action(SupplierAgreementVersion $version, array $modelData): SupplierAgreementVersion
    {
        $this->asAction = true;
        $this->initialisationFromGroup($version->group, $modelData);

        return $this->handle($version, $this->validatedData);
    }
}
