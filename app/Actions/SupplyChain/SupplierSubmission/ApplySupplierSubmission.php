<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What accepting a submission changes in aiku. Company details only change the fields staff ticked (the name through
 * company_name, which the supplier name follows), and never who
 * can sign in: that lives in the portal contacts. Pictures are added to the supplier product, which keeps them for
 * internal use, never on a website. Returns what it did, kept on the submission.
 */
class ApplySupplierSubmission
{
    use AsAction;
    use WithAttachMediaToModel;

    public const array COMPANY_FIELDS = ['name', 'contact_name', 'phone', 'contact_website'];

    /**
     * @param  array{shop_id?: int|null, fields?: array<int, string>|null}  $options
     * @return array<string, mixed>|null
     */
    public function handle(SupplierSubmission $supplierSubmission, array $options = []): ?array
    {
        $data = $supplierSubmission->data;

        return match ($supplierSubmission->type) {
            SupplierSubmissionTypeEnum::COMPANY_DETAILS => $this->companyDetails($supplierSubmission, $data, Arr::get($options, 'fields') ?? self::COMPANY_FIELDS),
            SupplierSubmissionTypeEnum::PHOTOS => $this->photos($supplierSubmission),
            SupplierSubmissionTypeEnum::REFERRAL => $this->referral($supplierSubmission, $data, (int) Arr::get($options, 'shop_id')),
            SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT => $this->complianceDocument($supplierSubmission),
            default => null,
        };
    }

    /**
     * @return array{fields: array<string, string>}
     */
    private function companyDetails(SupplierSubmission $supplierSubmission, array $data, array $fields): array
    {
        $changes = Arr::only($data, array_values(array_intersect($fields, self::COMPANY_FIELDS)));
        if ($changes !== []) {
            $update = Arr::except($changes, 'name');
            if (Arr::has($changes, 'name')) {
                $update['company_name'] = $changes['name'];
            }
            UpdateSupplier::make()->action($supplierSubmission->supplier, $update, strict: false);
        }

        return ['fields' => $changes];
    }

    /**
     * @return array{media_ids: array<int, int>}
     */
    private function photos(SupplierSubmission $supplierSubmission): array
    {
        $supplierProduct = $supplierSubmission->supplierProduct;
        if (!$supplierProduct) {
            throw ValidationException::withMessages(['state' => __('The product of these pictures no longer exists')]);
        }
        $mediaIds = [];

        foreach ($supplierSubmission->attachments as $attachment) {
            /** @var Media $attachment */
            $media = StoreMediaFromFile::run($supplierProduct, [
                'path'         => $attachment->getPath(),
                'originalName' => $attachment->name,
                'extension'    => pathinfo($attachment->file_name, PATHINFO_EXTENSION),
                'checksum'     => md5_file($attachment->getPath()),
            ], 'image');
            $this->attachMediaToModel($supplierProduct, $media, 'image');
            $mediaIds[] = $media->id;
        }

        if (!$supplierProduct->image_id && $mediaIds !== []) {
            $supplierProduct->update(['image_id' => $mediaIds[0]]);
        }

        return ['media_ids' => $mediaIds];
    }

    /**
     * @return array{prospect_id: int, shop_id: int}
     */
    private function referral(SupplierSubmission $supplierSubmission, array $data, int $shopId): array
    {
        $shop     = Shop::where('group_id', $supplierSubmission->group_id)->findOrFail($shopId);
        $prospect = StoreProspect::make()->action($shop, array_filter([
            'company_name' => Arr::get($data, 'company_name'),
            'contact_name' => Arr::get($data, 'contact_name'),
            'email'        => Arr::get($data, 'email'),
            'phone'        => Arr::get($data, 'phone'),
            'data'         => [
                'referred_by_supplier_id' => $supplierSubmission->supplier_id,
                'supplier_submission_id'  => $supplierSubmission->id,
                'country'                 => Arr::get($data, 'country'),
                'website'                 => Arr::get($data, 'contact_website'),
                'products_of_interest'    => Arr::get($data, 'products_of_interest'),
                'note'                    => Arr::get($data, 'note'),
            ],
        ], fn ($value) => $value !== null));

        return ['prospect_id' => $prospect->id, 'shop_id' => $shop->id];
    }

    /**
     * @return array{media_ids: array<int, int>}
     */
    private function complianceDocument(SupplierSubmission $supplierSubmission): array
    {
        $supplier = $supplierSubmission->supplier;
        $mediaIds = [];

        foreach ($supplierSubmission->attachments as $attachment) {
            if (!$supplier->attachments()->where('media_id', $attachment->id)->exists()) {
                $supplier->attachments()->attach($attachment->id, [
                    'group_id' => $supplier->group_id,
                    'scope'    => $attachment->pivot->scope,
                    'caption'  => trim($attachment->pivot->caption.($supplierSubmission->expires_on ? ' · '.__('expires :date', ['date' => $supplierSubmission->expires_on->toDateString()]) : '')),
                    'data'     => '{}',
                ]);
            }
            $mediaIds[] = $attachment->id;
        }

        return ['media_ids' => $mediaIds];
    }
}
