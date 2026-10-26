<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-16h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\Design\CancelProductDevelopmentArtworkInAiku;
use App\Actions\Goods\ProductDevelopment\Design\DeleteProductDevelopmentDesignSection;
use App\Actions\Goods\ProductDevelopment\Design\DownloadProductDevelopmentArtwork;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentArtworks;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignLayout;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Actions\Goods\ProductDevelopment\Design\UpdateProductDevelopmentArtworkLayout;
use App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentArtwork;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentArtworkVersion;
use App\Models\Goods\ProductDevelopmentDesignSection;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Design tab: the concept at the top, then each design section with one row per SKU showing its
 * current artwork and status. The review and its discussion open in a side panel per SKU.
 */
class GetProductDevelopmentDesignOverview
{
    use AsAction;
    use WithProductDevelopmentAuthorisation;

    /**
     * @var array<int, int> comment count by artwork id
     */
    private array $commentCounts = [];

    /**
     * @var array<int, string> label fingerprint by item id, worked out once per SKU
     */
    private array $labelHashes = [];

    private bool $canCheckPackagingSamples = false;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer): array
    {
        $access    = $this->productDevelopmentAccess();
        $canManage = $access->canManageArtwork($viewer, $productDevelopment);
        $canReview = $access->canApproveDesign($viewer, $productDevelopment);
        $this->canCheckPackagingSamples = $access->canDesign($viewer, $productDevelopment);
        $locked    = ProductDevelopmentDesignRounds::isArtworkLocked($productDevelopment);
        $items     = $productDevelopment->items()->with('tradeUnit')->orderBy('position')->orderBy('id')->get();
        $sections  = $productDevelopment->designSections()->with(['artworks.media', 'artworks.updatedBy', 'artworks.reviewedBy', 'artworks.versions.submittedBy', 'artworks.versions.reviewedBy', 'artworks.packagingSamplePhotos.media', 'artworks.packagingSampleReviewedBy'])->get();

        $this->commentCounts = $productDevelopment->comments()
            ->where('model_type', 'ProductDevelopmentArtwork')
            ->selectRaw('model_id, count(*) as total')
            ->groupBy('model_id')
            ->pluck('total', 'model_id')
            ->all();

        return [
            'concept'   => $this->concept($productDevelopment),
            'sections'  => $sections->map(fn (ProductDevelopmentDesignSection $section) => $this->section($productDevelopment, $section, $items, $canManage && !$locked, $canReview && !$locked))->values()->all(),
            'can_manage' => $canManage && !$locked,
            'locked'    => $locked,
            'max_mb'    => (int) (UploadProductDevelopmentArtwork::MAX_KILOBYTES / 1024),
            'routes'    => [
                'section_store' => ['name' => 'grp.models.product_development.design_section.store', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function concept(ProductDevelopment $productDevelopment): array
    {
        $latest = ProductDevelopmentDesignRounds::latest($productDevelopment, ProductDevelopmentDesignRoundEnum::CONCEPT);

        return [
            'latest' => $latest ? [
                'version'      => $latest->version,
                'status'       => [
                    'value' => $latest->status->value,
                    'label' => ProductDevelopmentDesignRoundStatusEnum::labels()[$latest->status->value],
                    'color' => $latest->status->color(),
                    'icon'  => $latest->status->icon(),
                ],
                'submitted_by' => $latest->submittedBy?->contact_name ?: $latest->submittedBy?->username,
                'submitted_at' => ($latest->submitted_at ?? $latest->created_at)?->toIso8601String(),
                ...ShowProductDevelopmentDesign::content($latest),
            ] : null,
            'route'  => ['name' => 'grp.product_developments.design', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'round' => ProductDevelopmentDesignRoundEnum::CONCEPT->value]],
        ];
    }

    /**
     * @param Collection<int, ProductDevelopmentItem> $items
     *
     * @return array<string, mixed>
     */
    private function section(ProductDevelopment $productDevelopment, ProductDevelopmentDesignSection $section, Collection $items, bool $canManage, bool $canReview): array
    {
        $artworks = $section->artworks->keyBy('product_development_item_id');
        $approved = $items->filter(fn (ProductDevelopmentItem $item) => $artworks->get($item->id)?->status === ProductDevelopmentArtworkStatusEnum::APPROVED)->count();
        $files    = $items->filter(fn (ProductDevelopmentItem $item) => $this->hasFile($artworks->get($item->id)))->count();

        return [
            'id'                 => $section->id,
            'name'               => $section->name,
            'description'        => $section->description,
            'packaging_supplier' => $section->packaging_supplier,
            'editor_kind'        => $section->editor_kind,
            'needs_compliance_text' => $section->needs_compliance_text,
            'progress'           => ['done' => $approved, 'total' => $items->count()],
            'files'              => $files,
            'can_delete'         => $canManage && DeleteProductDevelopmentDesignSection::isEmpty($section),
            'rows'               => $items->map(fn (ProductDevelopmentItem $item) => $this->row($productDevelopment, $section, $item, $artworks->get($item->id), $canManage, $canReview))->values()->all(),
            'routes'             => [
                'update' => ['name' => 'grp.models.product_development.design_section.update', 'parameters' => ['productDevelopmentDesignSection' => $section->id]],
                'delete' => ['name' => 'grp.models.product_development.design_section.delete', 'parameters' => ['productDevelopmentDesignSection' => $section->id]],
                'upload' => ['name' => 'grp.models.product_development.design_section.artwork.upload', 'parameters' => ['productDevelopmentDesignSection' => $section->id]],
                'aiku'   => ['name' => 'grp.models.product_development.design_section.artwork.aiku', 'parameters' => ['productDevelopmentDesignSection' => $section->id]],
                'zip'    => ['name' => 'grp.product_developments.design_section.zip', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'section' => $section->id]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function version(ProductDevelopment $productDevelopment, ProductDevelopmentArtwork $artwork, ProductDevelopmentArtworkVersion $version): array
    {
        $isCurrent = $version->version === $artwork->version;
        $hasFile   = $isCurrent ? $this->hasFile($artwork) : (bool) $version->media_id;

        return [
            'version'        => $version->version,
            'is_current'     => $isCurrent,
            'status'         => ProductDevelopmentArtworks::statusPayload((new ProductDevelopmentArtwork())->forceFill(['status' => $version->status])),
            'source'         => $version->source,
            'update_note'    => $version->update_note,
            'submitted_by'   => $version->submittedBy?->contact_name ?: $version->submittedBy?->username,
            'submitted_at'   => $version->submitted_at?->toIso8601String(),
            'review_comment' => $version->review_comment,
            'reviewed_by'    => $version->reviewedBy?->contact_name ?: $version->reviewedBy?->username,
            'reviewed_at'    => $version->reviewed_at?->toIso8601String(),
            'pdf_route'      => $hasFile ? ['name' => 'grp.product_developments.artwork.pdf', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id, 'version' => $version->version]] : null,
        ];
    }

    private function labelHash(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): string
    {
        return $this->labelHashes[$item->id] ??= ProductDevelopmentArtworks::labelHash($productDevelopment, $item);
    }

    private function hasFile(?ProductDevelopmentArtwork $artwork): bool
    {
        if (!$artwork) {
            return false;
        }

        return $artwork->source === ProductDevelopmentArtwork::SOURCE_AIKU
            ? ProductDevelopmentDesignLayout::hasSize($artwork->layout ?? [])
            : (bool) $artwork->media_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ProductDevelopment $productDevelopment, ProductDevelopmentDesignSection $section, ProductDevelopmentItem $item, ?ProductDevelopmentArtwork $artwork, bool $canManage, bool $canReview): array
    {
        $tradeUnit = $item->tradeUnit;
        $artwork?->setRelation('section', $section)->setRelation('item', $item)->setRelation('productDevelopment', $productDevelopment);
        $pdfRoute = $this->hasFile($artwork) ? ['name' => 'grp.product_developments.artwork.pdf', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]] : null;

        return [
            'item_id'   => $item->id,
            'code'      => $tradeUnit->code,
            'name'      => $tradeUnit->name,
            'image'     => $tradeUnit->image_id ? $tradeUnit->imageSources(96, 96) : null,
            'status'    => ProductDevelopmentArtworks::statusPayload($artwork),
            'artwork'   => $artwork ? [
                'id'             => $artwork->id,
                'source'         => $artwork->source,
                'version'        => $artwork->version,
                'file_name'      => $this->hasFile($artwork) ? DownloadProductDevelopmentArtwork::fileName($artwork) : null,
                'original_name'  => $artwork->media?->name,
                'size'           => $artwork->source === ProductDevelopmentArtwork::SOURCE_UPLOAD ? $artwork->media?->size : null,
                'updated_at'     => $artwork->artwork_updated_at?->toIso8601String(),
                'updated_by'     => $artwork->updatedBy?->contact_name ?: $artwork->updatedBy?->username,
                'update_note'    => $artwork->update_note,
                'review_comment' => $artwork->review_comment,
                'reviewed_by'    => $artwork->reviewedBy?->contact_name ?: $artwork->reviewedBy?->username,
                'reviewed_at'    => $artwork->reviewed_at?->toIso8601String(),
                'pdf_route'      => $pdfRoute,
                'editor_route'   => $artwork->source === ProductDevelopmentArtwork::SOURCE_AIKU ? ['name' => 'grp.product_developments.artwork', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]] : null,
                'can_edit_layout' => $canManage && UpdateProductDevelopmentArtworkLayout::isEditable($artwork),
                'can_review'     => $canReview && $artwork->status === ProductDevelopmentArtworkStatusEnum::IN_REVIEW,
                'approval_blocked' => $artwork->status === ProductDevelopmentArtworkStatusEnum::IN_REVIEW ? ProductDevelopmentArtworks::approvalBlockedReason($artwork) : null,
                'review_route'   => ['name' => 'grp.models.product_development.artwork.review', 'parameters' => ['productDevelopmentArtwork' => $artwork->id]],
                'can_cancel_aiku' => $canManage && CancelProductDevelopmentArtworkInAiku::isCancellable($artwork),
                'cancel_aiku_route' => ['name' => 'grp.models.product_development.artwork.aiku.cancel', 'parameters' => ['productDevelopmentArtwork' => $artwork->id]],
                'comments'       => (int) ($this->commentCounts[$artwork->id] ?? 0),
                'needs_recheck'  => $artwork->status === ProductDevelopmentArtworkStatusEnum::APPROVED && $artwork->approved_label_hash !== null
                    && $artwork->approved_label_hash !== $this->labelHash($productDevelopment, $item),
                'versions'       => $artwork->versions->map(fn (ProductDevelopmentArtworkVersion $version) => $this->version($productDevelopment, $artwork, $version))->values()->all(),
                'comments_route' => ['name' => 'grp.product_developments.json.artwork_comments', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]],
                'packaging_sample' => [
                    'status'      => $artwork->packaging_sample_status,
                    'note'        => $artwork->packaging_sample_note,
                    'reviewed_by' => $artwork->packagingSampleReviewedBy?->contact_name ?: $artwork->packagingSampleReviewedBy?->username,
                    'reviewed_at' => $artwork->packaging_sample_reviewed_at?->toIso8601String(),
                    'photos'      => $artwork->packagingSamplePhotos->map(fn (ProductDevelopmentFile $photo) => UploadProductDevelopmentFiles::photoRow($photo, $this->canCheckPackagingSamples))->all(),
                    'can_check'   => $this->canCheckPackagingSamples,
                    'routes'      => [
                        'photos' => ['name' => 'grp.models.product_development.artwork.packaging_sample.photos', 'parameters' => ['productDevelopmentArtwork' => $artwork->id]],
                        'review' => ['name' => 'grp.models.product_development.artwork.packaging_sample.review', 'parameters' => ['productDevelopmentArtwork' => $artwork->id]],
                    ],
                ],
            ] : null,
            'can_upload' => $canManage,
            'label_content_route' => ['name' => 'grp.product_developments.json.label_content', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'item' => $item->id]],
        ];
    }
}
