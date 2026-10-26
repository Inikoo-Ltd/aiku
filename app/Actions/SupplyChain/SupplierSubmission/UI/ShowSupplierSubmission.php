<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierSubmission\ApplySupplierSubmission;
use App\Actions\SupplyChain\SupplierSubmission\DecideSupplierSubmission;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierSubmission extends OrgAction
{
    use WithSupplyChainAuthorisation;

    public static function summary(SupplierSubmission $submission, ?string $productCode = null): string
    {
        $data = $submission->data ?? [];

        return match ($submission->type) {
            SupplierSubmissionTypeEnum::COMPANY_DETAILS => implode(', ', array_keys(Arr::except($data, ['note']))),
            SupplierSubmissionTypeEnum::PHOTOS => trim(($productCode ?? '').' '.Arr::get($data, 'picture_kind', '')),
            SupplierSubmissionTypeEnum::REFERRAL => trim(Arr::get($data, 'company_name', '').' · '.Arr::get($data, 'country', ''), ' ·'),
            SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL => (string) Arr::get($data, 'name', ''),
            SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT => trim(Arr::get($data, 'document_type', '').($submission->expires_on ? ' · '.__('expires :date', ['date' => $submission->expires_on->toDateString()]) : '')),
            SupplierSubmissionTypeEnum::PRICE_CHANGE => trim(($productCode ?? '').' → '.Arr::get($data, 'proposed_price', '')),
        };
    }

    public function handle(SupplierSubmission $supplierSubmission): SupplierSubmission
    {
        return $supplierSubmission->load(['supplier.currency', 'supplierProduct', 'portalContact', 'decidedBy', 'attachments']);
    }

    public function asController(SupplierSubmission $supplierSubmission, ActionRequest $request): SupplierSubmission
    {
        $this->initialisationFromGroup($supplierSubmission->group, $request);

        return $this->handle($supplierSubmission);
    }

    /**
     * For a referral, the prospects and customers that already have its email or phone, so staff do not add it twice.
     *
     * @return array<int, array{kind: string, name: string|null, email: string|null}>
     */
    private function duplicates(SupplierSubmission $submission): array
    {
        if ($submission->type !== SupplierSubmissionTypeEnum::REFERRAL) {
            return [];
        }

        $email = Arr::get($submission->data, 'email');
        $phone = Arr::get($submission->data, 'phone');
        $match = fn ($query) => $query->where('group_id', $submission->group_id)
            ->where(fn ($query) => $query->when($email, fn ($q) => $q->orWhere('email', $email))->when($phone, fn ($q) => $q->orWhere('phone', $phone)));

        return collect()
            ->merge(Prospect::query()->tap($match)->limit(5)->get(['company_name', 'contact_name', 'email'])->map(fn ($row) => ['kind' => __('Prospect'), 'name' => $row->company_name ?? $row->contact_name, 'email' => $row->email]))
            ->merge(Customer::query()->tap($match)->limit(5)->get(['name', 'email'])->map(fn ($row) => ['kind' => __('Customer'), 'name' => $row->name, 'email' => $row->email]))
            ->values()
            ->all();
    }

    public function htmlResponse(SupplierSubmission $supplierSubmission, ActionRequest $request): Response
    {
        $supplier = $supplierSubmission->supplier;
        $current  = $supplierSubmission->type === SupplierSubmissionTypeEnum::COMPANY_DETAILS
            ? collect(ApplySupplierSubmission::COMPANY_FIELDS)->mapWithKeys(fn (string $field) => [$field => $supplier->{$field}])->all()
            : [];

        return Inertia::render('SupplyChain/SupplierSubmission', [
            'breadcrumbs' => array_merge(IndexSupplierSubmissions::make()->getBreadcrumbs(), [[
                'type'   => 'simple',
                'simple' => ['label' => '#'.$supplierSubmission->id],
            ]]),
            'title'       => __('Supplier submission'),
            'pageHead'    => [
                'title' => SupplierSubmissionTypeEnum::labels()[$supplierSubmission->type->value].' · '.$supplier->code,
                'model' => __('Supplier submission'),
                'icon'  => ['icon' => ['fal', 'fa-inbox-in'], 'title' => __('Supplier submission')],
            ],
            'submission'  => [
                'id'           => $supplierSubmission->id,
                'type'         => $supplierSubmission->type->value,
                'type_label'   => SupplierSubmissionTypeEnum::labels()[$supplierSubmission->type->value],
                'state'        => $supplierSubmission->state->value,
                'state_label'  => SupplierSubmissionStateEnum::labels()[$supplierSubmission->state->value],
                'data'         => $supplierSubmission->data,
                'current'      => $current,
                'feedback'     => $supplierSubmission->feedback,
                'expires_on'   => $supplierSubmission->expires_on?->toDateString(),
                'submitted_at' => $supplierSubmission->submitted_at,
                'decided_at'   => $supplierSubmission->decided_at,
                'decided_by'   => $supplierSubmission->decidedBy?->contact_name,
                'applied'      => $supplierSubmission->applied,
                'applied_at'   => $supplierSubmission->applied_at,
                'sent_by'      => $supplierSubmission->portalContact?->email,
                'supplier'     => ['code' => $supplier->code, 'name' => $supplier->name, 'url' => route('grp.majordomo.redirect_supplier', [$supplier->id])],
                'product'      => $supplierSubmission->supplierProduct ? ['code' => $supplierSubmission->supplierProduct->code, 'name' => $supplierSubmission->supplierProduct->name, 'cost' => $supplierSubmission->supplierProduct->cost, 'currency' => $supplier->currency?->code] : null,
                'files'        => $supplierSubmission->attachments->map(fn (Media $media) => [
                    'name'  => $media->name.'.'.pathinfo($media->file_name, PATHINFO_EXTENSION),
                    'url'   => route('grp.media.download', $media->ulid),
                    'image' => str_starts_with((string) $media->mime_type, 'image/'),
                ])->values()->all(),
                'duplicates'   => $this->duplicates($supplierSubmission),
            ],
            'decide'      => [
                'can'            => $this->canEdit && $supplierSubmission->state !== SupplierSubmissionStateEnum::DRAFT,
                'route'          => ['name' => 'grp.models.supplier_submission.decide', 'parameters' => ['supplierSubmission' => $supplierSubmission->id]],
                'states'         => collect(SupplierSubmissionStateEnum::labels())->except([SupplierSubmissionStateEnum::DRAFT->value, SupplierSubmissionStateEnum::RECEIVED->value])->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'applies'        => in_array($supplierSubmission->type, [SupplierSubmissionTypeEnum::COMPANY_DETAILS, SupplierSubmissionTypeEnum::PHOTOS, SupplierSubmissionTypeEnum::REFERRAL, SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT], true),
                'shops'          => $supplierSubmission->type === SupplierSubmissionTypeEnum::REFERRAL ? DecideSupplierSubmission::shopOptions($supplierSubmission->group_id) : [],
                'company_fields' => ApplySupplierSubmission::COMPANY_FIELDS,
            ],
        ]);
    }
}
