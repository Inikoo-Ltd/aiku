<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\Procurement\OrgSupplierProducts\NotifyPurchasingOfSupplierProductAvailability;
use App\Actions\SupplyChain\SupplierPortalContact\SyncSupplierPortalContact;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Enums\SupplyChain\SupplierPortalContact\SupplierPortalContactDesignationEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductAvailabilityStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductUnavailableReasonEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SupplyChain\SupplyChainAttachmentScopeEnum;
use App\Models\SupplyChain\SupplierPortalContact;
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
 * changes until staff accept it, except a price change: its product goes on hold (no purchase order can be raised)
 * and its buyers are told, again each time the supplier edits it. A new product idea can be kept as a draft, a
 * submission we asked more about can be completed and sent again, and a price change edited until we decide.
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
            $replacesData = in_array($type, [SupplierSubmissionTypeEnum::COMPANY_DETAILS, SupplierSubmissionTypeEnum::CONTACT], true);
            $submission->update([...$fields, 'data' => $replacesData ? $fields['data'] : [...$submission->data, ...$fields['data']]]);
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

        if ($type === SupplierSubmissionTypeEnum::PRICE_CHANGE) {
            $this->holdForPriceChange($submission);
        }

        return $submission;
    }

    /**
     * A product already taken off for another reason keeps that reason; it only learns the new cost asked.
     */
    private function holdForPriceChange(SupplierSubmission $submission): void
    {
        $supplierProduct    = $submission->supplierProduct()->firstOrFail();
        $heldForOtherReason = $supplierProduct->availability_status === SupplierProductAvailabilityStatusEnum::UNAVAILABLE
            && $supplierProduct->unavailable_reason !== SupplierProductUnavailableReasonEnum::PRICE_CHANGE_PROPOSED;

        $supplierProduct = UpdateSupplierProduct::make()->action($supplierProduct, [
            'availability_new_cost' => $submission->data['proposed_price'],
            ...($heldForOtherReason ? [] : [
                'availability_status' => SupplierProductAvailabilityStatusEnum::UNAVAILABLE->value,
                'unavailable_reason'  => SupplierProductUnavailableReasonEnum::PRICE_CHANGE_PROPOSED->value,
                'availability_note'   => $submission->data['reason'] ?? null,
            ]),
        ], strict: false);

        NotifyPurchasingOfSupplierProductAvailability::run($supplierProduct, null);
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
                'supplier_product_id' => $this->submission
                    ? ['required', Rule::in([$this->submission->supplier_product_id])]
                    : ['required', ...array_slice($product, 1), function (string $attribute, mixed $value, \Closure $fail) {
                        $hasOpenPriceChange = SupplierSubmission::where('supplier_id', $this->link->supplier_id)
                            ->where('type', SupplierSubmissionTypeEnum::PRICE_CHANGE)
                            ->where('supplier_product_id', $value)
                            ->whereIn('state', [SupplierSubmissionStateEnum::RECEIVED, SupplierSubmissionStateEnum::UNDER_REVIEW, SupplierSubmissionStateEnum::MORE_INFORMATION_REQUESTED])
                            ->exists();
                        if ($hasOpenPriceChange) {
                            $fail(__('You already proposed a price change for this product. Edit that one in the list below.'));
                        }
                    }],
                'proposed_price'      => ['required', 'numeric', 'min:0'],
                'effective_from'      => ['nullable', 'date'],
                'reason'              => ['required', 'string', 'max:2000'],
            ],
            SupplierSubmissionTypeEnum::CONTACT => [
                'designation' => ['required', Rule::enum(SupplierPortalContactDesignationEnum::class)],
                'name'        => ['required', 'string', 'max:255'],
                'email'       => ['required', 'email:rfc', 'max:255', SyncSupplierPortalContact::notUsedByAnotherSupplierRule($this->link->supplier), $this->notAlreadyOurContactRule()],
                'mobile'      => $text(64),
                'whatsapp'    => $text(64),
                'wechat'      => $text(64),
                'note'        => $text(2000),
            ],
        };

        if ($this->type === SupplierSubmissionTypeEnum::COMPANY_DETAILS) {
            $rules['phone'][] = 'required_without_all:contact_website,address';
        }

        return $rules;
    }

    /**
     * An email the supplier already has, even one we stopped, is changed by staff only: adding it again would rename
     * that contact or let a stopped one sign in again.
     */
    private function notAlreadyOurContactRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $email = mb_strtolower(trim((string) $value));
            if (in_array($email, SyncSupplierPortalContact::emails($this->link->supplier), true)
                || SupplierPortalContact::where('supplier_id', $this->link->supplier_id)->where('email', $email)->exists()) {
                $fail(__('We already have this email. To change it, ask your contact at :group.', ['group' => $this->link->group->name]));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function submissionMessages(): array
    {
        return ['phone.required_without_all' => __('Change at least the phone, the website or the address.')];
    }

    public function asController(string $token, string $type, Request $request): RedirectResponse
    {
        $this->link = SupplierShareLink::findLiveByToken($token);
        $this->type = SupplierSubmissionTypeEnum::tryFrom($type) ?? abort(404);
        abort_if($this->link->needsCode(), 403);

        $this->handle($this->link, $this->type, Validator::make($request->all(), $this->submissionRules(), $this->submissionMessages())->validate());

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
        abort_if($this->link->needsCode() || !$this->submission->state->isEditableBySupplier($this->type), 403);

        $this->handle($this->link, $this->type, Validator::make($request->all(), $this->submissionRules(), $this->submissionMessages())->validate(), $this->submission);

        return back()->with('submitted', $this->type->value);
    }
}
