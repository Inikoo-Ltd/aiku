<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A supplier sends something from its page. The supplier comes from the link, never from what it posts, so a
 * product or a submission of another supplier is not found. Files are kept on the submission only; nothing in aiku
 * changes until staff accept it. A new product idea can be kept as a draft, and a submission we asked more about
 * can be completed and sent again.
 */
class StoreSupplierPortalSubmission
{
    use AsAction;

    public const int MAX_FILES = 10;

    public const int MAX_FILE_KB = 15360;

    private SupplierShareLink $link;

    private SupplierSubmissionTypeEnum $type;

    private ?SupplierSubmission $submission = null;

    /**
     * @param  array<string, mixed>  $modelData
     */
    public function handle(SupplierShareLink $link, SupplierSubmissionTypeEnum $type, array $modelData, ?SupplierSubmission $submission = null): SupplierSubmission
    {
        $files   = Arr::pull($modelData, 'files', []);
        $isDraft = $type === SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL && filter_var(Arr::pull($modelData, 'draft'), FILTER_VALIDATE_BOOLEAN);
        $state   = $isDraft ? SupplierSubmissionStateEnum::DRAFT : SupplierSubmissionStateEnum::RECEIVED;

        $fields = [
            'supplier_product_id' => Arr::pull($modelData, 'supplier_product_id'),
            'expires_on'          => Arr::pull($modelData, 'expires_on'),
            'data'                => array_filter($modelData, fn ($value) => $value !== null && $value !== ''),
            'state'               => $state,
            'submitted_at'        => $isDraft ? null : now(),
        ];

        if ($submission) {
            $submission->update([...$fields, 'data' => [...$submission->data, ...$fields['data']]]);
        } else {
            $submission = SupplierSubmission::create([
                ...$fields,
                'group_id'                   => $link->group_id,
                'supplier_id'                => $link->supplier_id,
                'supplier_share_link_id'     => $link->id,
                'supplier_portal_contact_id' => $link->supplier_portal_contact_id,
                'type'                       => $type,
            ]);
        }

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            SaveModelAttachment::make()->action($submission, [
                'path'         => $file->getPathname(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->guessExtension() ?? 'bin',
                'scope'        => $type === SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT ? (string) Arr::get($submission->data, 'document_type', SupplyChainAttachmentScopeEnum::OTHER->value) : $type->value,
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);
        }

        return $submission;
    }

    public function submissionRules(): array
    {
        $isDraft  = $this->type === SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL && filter_var(request()->input('draft'), FILTER_VALIDATE_BOOLEAN);
        $required = $isDraft ? 'nullable' : 'required';
        $product  = ['nullable', 'integer', Rule::exists('supplier_products', 'id')->where('supplier_id', $this->link->supplier_id)->whereNull('deleted_at')];
        $files    = fn (string $mimes, bool $needed) => [
            'files'   => [$needed && !$this->submission ? 'required' : 'nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimes:'.$mimes, 'max:'.self::MAX_FILE_KB],
        ];
        $text     = fn (int $max = 255) => ['nullable', 'string', 'max:'.$max];

        $rules = match ($this->type) {
            SupplierSubmissionTypeEnum::COMPANY_DETAILS => [
                'name'            => $text(),
                'contact_name'    => $text(),
                'phone'           => $text(64),
                'contact_website' => $text(),
                'address'         => $text(1000),
                'note'            => $text(2000),
            ],
            SupplierSubmissionTypeEnum::PHOTOS => [
                'supplier_product_id' => ['required', ...array_slice($product, 1)],
                'picture_kind'        => ['required', Rule::in(['product', 'detail', 'packaging', 'lifestyle'])],
                'note'                => $text(2000),
                ...$files('jpg,jpeg,png,webp', true),
            ],
            SupplierSubmissionTypeEnum::REFERRAL => [
                'company_name'         => ['required', 'string', 'max:255'],
                'country'              => ['required', 'string', 'max:100'],
                'contact_website'      => $text(),
                'contact_name'         => $text(),
                'email'                => ['required_without:phone', 'nullable', 'email:rfc', 'max:255'],
                'phone'                => ['required_without:email', 'nullable', 'string', 'max:64'],
                'products_of_interest' => $text(2000),
                'note'                 => $text(2000),
                'permission_confirmed' => ['accepted'],
            ],
            SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL => [
                'name'           => ['required', 'string', 'max:255'],
                'description'    => [$required, 'string', 'max:5000'],
                'proposed_price' => [$required, 'numeric', 'min:0'],
                'currency'       => [$required, 'string', 'size:3'],
                'unit_basis'     => [$required, 'string', 'max:255'],
                'moq'            => $text(),
                'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'materials'      => $text(2000),
                'dimensions'     => $text(),
                'packaging'      => $text(2000),
                'draft'          => ['sometimes', 'boolean'],
                ...$files('jpg,jpeg,png,webp,pdf', false),
            ],
            SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT => [
                'document_type'       => ['required', Rule::in(collect(SupplyChainAttachmentScopeEnum::cases())->map->value->diff(['Price list', 'Catalogue'])->values()->all())],
                'supplier_product_id' => $product,
                'expires_on'          => ['nullable', 'date', 'after:today'],
                'note'                => $text(2000),
                ...$files('pdf,jpg,jpeg,png', true),
            ],
            SupplierSubmissionTypeEnum::PRICE_CHANGE => [
                'supplier_product_id' => ['required', ...array_slice($product, 1)],
                'proposed_price'      => ['required', 'numeric', 'min:0'],
                'effective_from'      => ['nullable', 'date'],
                'reason'              => ['required', 'string', 'max:2000'],
            ],
        };

        if ($this->type === SupplierSubmissionTypeEnum::COMPANY_DETAILS) {
            $rules['name'][] = 'required_without_all:contact_name,email,phone,contact_website,address';
        }

        return $rules;
    }

    public function asController(string $token, string $type, Request $request): RedirectResponse
    {
        $this->link = SupplierShareLink::findLiveByToken($token);
        $this->type = SupplierSubmissionTypeEnum::tryFrom($type) ?? abort(404);
        abort_if($this->link->needsCode(), 403);

        $this->handle($this->link, $this->type, Validator::make($request->all(), $this->submissionRules())->validate());

        return back()->with('submitted', $this->type->value);
    }

    /**
     * Completes a draft, or a submission we asked more about, and sends it again.
     */
    public function inSubmission(string $token, int $submission, Request $request): RedirectResponse
    {
        $this->link       = SupplierShareLink::findLiveByToken($token);
        $this->submission = SupplierSubmission::where('supplier_id', $this->link->supplier_id)->findOrFail($submission);
        $this->type       = $this->submission->type;
        abort_if($this->link->needsCode() || !$this->submission->state->isEditableBySupplier(), 403);

        $this->handle($this->link, $this->type, Validator::make($request->all(), $this->submissionRules())->validate(), $this->submission);

        return back()->with('submitted', $this->type->value);
    }
}
