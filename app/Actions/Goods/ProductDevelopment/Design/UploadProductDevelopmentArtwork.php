<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-15h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentDesignSection;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Uploads or replaces the PDF of one SKU in a design section. The new file replaces the old one, goes
 * straight to review (an approved artwork loses its approval) and can carry a note on what changed.
 * A layout made in Aiku can be replaced by a PDF once it is in review, not while it is being drawn.
 */
class UploadProductDevelopmentArtwork extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const int MAX_KILOBYTES = 51200;

    private ProductDevelopmentDesignSection $section;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->section->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageArtwork($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentDesignSection $section, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $productDevelopment = $section->productDevelopment;
        /** @var UploadedFile $file */
        $file = $modelData['file'];
        $item = ProductDevelopmentItem::findOrFail($modelData['product_development_item_id']);

        $artwork = DB::transaction(function () use ($section, $user, $modelData, $productDevelopment, $file, $item) {
            $media = UploadProductDevelopmentFiles::make()->storeMedia($productDevelopment, $file, ProductDevelopmentFileScopeEnum::ARTWORK_DRAFT, null);

            /** @var ProductDevelopmentArtwork $artwork */
            $artwork = $section->artworks()->firstOrNew(['product_development_item_id' => $item->id]);
            $artwork->fill([
                'product_development_id' => $productDevelopment->id,
                'source'                 => ProductDevelopmentArtwork::SOURCE_UPLOAD,
                'media_id'               => $media->id,
                'version'                => $artwork->version + 1,
                'status'                 => ProductDevelopmentArtworkStatusEnum::IN_REVIEW,
                'update_note'            => trim((string) ($modelData['update_note'] ?? '')) ?: null,
                'updated_by_id'          => $user->id,
                'artwork_updated_at'     => now(),
                'review_comment'         => null,
                'reviewed_by_id'         => null,
                'reviewed_at'            => null,
            ])->save();
            ProductDevelopmentArtworks::recordVersion($artwork);

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::ARTWORK_UPLOADED, [], [
                'artwork' => ProductDevelopmentArtworks::label($artwork),
                'version' => $artwork->version,
                'note'    => $artwork->update_note,
            ]);

            return $artwork;
        });

        ProductDevelopmentArtworks::notifyReviewers($artwork, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment->refresh(), $user);

        return $artwork;
    }

    public function rules(): array
    {
        return [
            'product_development_item_id' => ['required', 'integer', Rule::exists('product_development_items', 'id')->where('product_development_id', $this->section->product_development_id)],
            'file'                        => ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'extensions:pdf', 'mimes:pdf'],
            'update_note'                 => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'file.extensions' => __('Upload the artwork as a PDF'),
            'file.mimes'      => __('Upload the artwork as a PDF'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentArtworks::canChange(ProductDevelopmentArtworks::changeTarget($this->section, (int) $this->get('product_development_item_id')))) {
            $validator->errors()->add('file', ProductDevelopmentDesignRounds::isArtworkLocked($this->section->productDevelopment)
                ? __('The artwork is signed off and locked')
                : __('Approve the concept first'));

            return;
        }

        if ($this->section->artworks()->where('product_development_item_id', (int) $this->get('product_development_item_id'))->where('source', ProductDevelopmentArtwork::SOURCE_AIKU)->whereNotIn('status', [ProductDevelopmentArtworkStatusEnum::IN_REVIEW, ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED])->exists()) {
            $validator->errors()->add('file', __('This artwork is being made in Aiku: a PDF can replace it once it is in review or sent back'));
        }
    }

    public function asController(ProductDevelopmentDesignSection $productDevelopmentDesignSection, ActionRequest $request): ProductDevelopmentArtwork
    {
        $this->section = $productDevelopmentDesignSection;
        $this->initialisationFromGroup($productDevelopmentDesignSection->productDevelopment->group, $request);

        return $this->handle($productDevelopmentDesignSection, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentDesignSection $section, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $this->asAction = true;
        $this->section  = $section;
        $this->initialisationFromGroup($section->productDevelopment->group, $modelData);

        return $this->handle($section, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
