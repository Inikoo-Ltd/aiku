<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-14h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\NotifyProductDevelopmentUsers;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentDesignSection;
use App\Models\Goods\ProductDevelopmentItem;
use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Models\SysAdmin\User;

/**
 * Every design section holds one artwork per SKU of the development. A SKU without a row has nothing
 * uploaded yet. Artwork can be made from day one; only a section that needs the compliance text (the
 * label) waits for it before approval. The artwork stage is done once every SKU of every section is approved.
 */
class ProductDevelopmentArtworks
{
    public const array DEFAULT_SECTIONS = [
        ['name' => 'Box', 'editor_kind' => ProductDevelopmentDesignLayout::KIND_BOX, 'needs_compliance_text' => false],
        ['name' => 'Label', 'editor_kind' => ProductDevelopmentDesignLayout::KIND_SHEET, 'needs_compliance_text' => true],
    ];

    public static function createDefaultSections(ProductDevelopment $productDevelopment): void
    {
        foreach (self::DEFAULT_SECTIONS as $position => $section) {
            $productDevelopment->designSections()->create([...$section, 'position' => $position + 1]);
        }
    }

    /**
     * The SKU's compliance text is approved once none of its required compliance items is missing.
     */
    public static function isComplianceTextApproved(ProductDevelopmentItem $item): bool
    {
        return !$item->checklistItems()
            ->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)
            ->get()
            ->contains(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->isMissing());
    }

    /**
     * Why this artwork cannot be approved yet, or null when it can. Compliance gives its OK first. A second
     * person always looks at a version: the Compliance OK is that second look for the owner, so the owner
     * approves their own upload once it is given, while any other approver never approves their own.
     */
    public static function approvalBlockedReason(ProductDevelopmentArtwork $artwork, User $reviewer): ?string
    {
        if ($artwork->compliance_ok_at === null) {
            return __('Compliance has not given its OK yet');
        }

        if ($artwork->updated_by_id === $reviewer->id && $artwork->productDevelopment->owner_id !== $reviewer->id) {
            return __('You uploaded this version, so someone else has to approve it');
        }

        return self::complianceTextBlockedReason($artwork);
    }

    /**
     * Why Compliance cannot give its OK on this version yet, or null when it can.
     */
    public static function complianceOkBlockedReason(ProductDevelopmentArtwork $artwork, User $user): ?string
    {
        if ($artwork->updated_by_id === $user->id) {
            return __('You uploaded this version, so someone else has to give the Compliance OK');
        }

        return self::complianceTextBlockedReason($artwork);
    }

    private static function complianceTextBlockedReason(ProductDevelopmentArtwork $artwork): ?string
    {
        return $artwork->section->needs_compliance_text && !self::isComplianceTextApproved($artwork->item)
            ? __('The compliance text of :sku is not approved yet', ['sku' => $artwork->item->tradeUnit->code])
            : null;
    }

    /**
     * @return array{done: int, total: int}
     */
    public static function progress(ProductDevelopmentDesignSection $section): array
    {
        $itemIds = $section->productDevelopment->items()->pluck('id');

        return [
            'done'  => $section->artworks()->whereIn('product_development_item_id', $itemIds)->where('status', ProductDevelopmentArtworkStatusEnum::APPROVED->value)->count(),
            'total' => $itemIds->count(),
        ];
    }

    public static function isAllApproved(ProductDevelopment $productDevelopment): bool
    {
        $itemIds  = $productDevelopment->items()->pluck('id');
        $sections = $productDevelopment->designSections()->count();

        if ($sections === 0) {
            return true;
        }

        $approved = $productDevelopment->artworks()
            ->whereIn('product_development_item_id', $itemIds)
            ->where('status', ProductDevelopmentArtworkStatusEnum::APPROVED->value)
            ->count();

        return $itemIds->isNotEmpty() && $approved === $sections * $itemIds->count();
    }

    public static function hasAnyArtwork(ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->artworks()->where('status', '!=', ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED->value)->exists()
            || $productDevelopment->artworks()->where(fn ($query) => $query->whereNotNull('media_id')->orWhere('source', ProductDevelopmentArtwork::SOURCE_AIKU))->exists();
    }

    /**
     * Artwork can change until it is signed off; after that only an artwork reopened by a rejected sample can.
     */
    public static function canChange(ProductDevelopmentArtwork|ProductDevelopment $target): bool
    {
        $productDevelopment = $target instanceof ProductDevelopment ? $target : $target->productDevelopment;

        return !ProductDevelopmentDesignRounds::isArtworkLocked($productDevelopment)
            || ($target instanceof ProductDevelopmentArtwork && $target->sample_reopened_at !== null);
    }

    /**
     * The section's artwork of a SKU, or the development when the SKU has none yet, to ask canChange about.
     */
    public static function changeTarget(ProductDevelopmentDesignSection $section, int $itemId): ProductDevelopmentArtwork|ProductDevelopment
    {
        return $section->artworks()->where('product_development_item_id', $itemId)->first() ?? $section->productDevelopment;
    }

    /**
     * Whether the artwork has a PDF to show: an uploaded file, or an Aiku layout with its size set.
     */
    public static function hasFile(ProductDevelopmentArtwork $artwork): bool
    {
        return $artwork->source === ProductDevelopmentArtwork::SOURCE_AIKU
            ? ProductDevelopmentDesignLayout::hasSize($artwork->layout ?? [])
            : (bool) $artwork->media_id;
    }

    /**
     * Keeps the history row of the artwork's current version in step with it: written when a version is
     * sent for review and updated when it is reviewed.
     */
    public static function recordVersion(ProductDevelopmentArtwork $artwork): void
    {
        $artwork->versions()->updateOrCreate(['version' => $artwork->version], [
            'source'          => $artwork->source,
            'media_id'        => $artwork->source === ProductDevelopmentArtwork::SOURCE_UPLOAD ? $artwork->media_id : null,
            'status'          => $artwork->status,
            'update_note'     => $artwork->update_note,
            'submitted_by_id' => $artwork->updated_by_id,
            'submitted_at'    => $artwork->artwork_updated_at,
            'review_comment'  => $artwork->review_comment,
            'reviewed_by_id'  => $artwork->reviewed_by_id,
            'reviewed_at'     => $artwork->reviewed_at,
        ]);
    }

    /**
     * A fingerprint of everything the label shows for a SKU (pack text, marks, pictograms and barcodes), to
     * tell when it changed after its artwork was approved.
     */
    public static function labelHash(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): string
    {
        $content = GetProductDevelopmentLabelContent::run($productDevelopment, $item->loadMissing('tradeUnit'));

        return hash('sha256', json_encode([$content['values'], $content['marks']]));
    }

    public static function statusPayload(?ProductDevelopmentArtwork $artwork): array
    {
        $status = $artwork?->status ?? ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED;

        return [
            'value' => $status->value,
            'label' => ProductDevelopmentArtworkStatusEnum::labels()[$status->value],
            'color' => $status->color(),
            'icon'  => $status->icon(),
        ];
    }

    /**
     * "Box · ACBFC-01", how an artwork is named in messages and activity.
     */
    public static function label(ProductDevelopmentArtwork $artwork): string
    {
        return $artwork->section->name.' · '.$artwork->item->tradeUnit->code;
    }

    /**
     * A new version goes to Compliance first. With nobody in the Compliance role the owner is told to add someone,
     * since the version cannot be approved without the Compliance OK.
     */
    public static function notifyCompliance(ProductDevelopmentArtwork $artwork, User $actor): void
    {
        $productDevelopment = $artwork->productDevelopment;
        $compliance         = NotifyProductDevelopmentUsers::make()->usersWithRole($productDevelopment, ProductDevelopmentMemberRoleEnum::COMPLIANCE);
        $changed            = $artwork->update_note ? ' '.__('What changed: :note', ['note' => $artwork->update_note]) : '';

        if ($compliance->isEmpty()) {
            NotifyProductDevelopmentUsers::run(
                $productDevelopment,
                [$productDevelopment->owner],
                UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
                __(':artwork of :code waits for a Compliance OK', ['artwork' => self::label($artwork), 'code' => $productDevelopment->code]),
                __('Nobody in the team has the Compliance role. Add someone so they can give the Compliance OK.'),
                tab: 'design',
            );

            return;
        }

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            $compliance,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            __(':artwork of :code is ready for the Compliance OK', ['artwork' => self::label($artwork), 'code' => $productDevelopment->code]),
            __('Open the Design tab to check it and give the Compliance OK.').$changed,
            tab: 'design',
            except: $actor,
        );
    }

    /**
     * Once Compliance gave its OK, the owner and the approvers are asked for the final approval.
     */
    public static function notifyReviewers(ProductDevelopmentArtwork $artwork, User $actor): void
    {
        $productDevelopment = $artwork->productDevelopment;
        $notifier           = NotifyProductDevelopmentUsers::make();
        $reviewers          = collect(ProductDevelopmentAccess::DESIGN_APPROVER_ROLES)
            ->flatMap(fn (ProductDevelopmentMemberRoleEnum $role) => $notifier->usersWithRole($productDevelopment, $role))
            ->push($productDevelopment->owner);

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            $reviewers,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            __(':artwork of :code has the Compliance OK', ['artwork' => self::label($artwork), 'code' => $productDevelopment->code]),
            __('Open the Design tab to approve it or ask for changes.'),
            tab: 'design',
            except: $actor,
        );
    }

    public static function notifyDesigners(ProductDevelopmentArtwork $artwork, User $actor): void
    {
        $productDevelopment = $artwork->productDevelopment;
        $approved           = $artwork->status === ProductDevelopmentArtworkStatusEnum::APPROVED;

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            NotifyProductDevelopmentUsers::make()->usersWithRole($productDevelopment, ProductDevelopmentMemberRoleEnum::DESIGNER)->push($artwork->updatedBy),
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            $approved
                ? __(':artwork of :code is approved', ['artwork' => self::label($artwork), 'code' => $productDevelopment->code])
                : __('Changes required on :artwork of :code', ['artwork' => self::label($artwork), 'code' => $productDevelopment->code]),
            $approved ? __('It is ready for printing once the final artwork is signed off.') : (string) $artwork->review_comment,
            tab: 'design',
            except: $actor,
        );
    }
}
