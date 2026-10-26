<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-01h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Models\Goods\ProductDevelopment;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

class RecordProductDevelopmentActivity
{
    public const string STAGE_MOVED = 'stage_moved';

    public const string SKUS_ADDED = 'skus_added';

    public const string MEMBERS_ADDED = 'members_added';

    public const string MEMBERS_REMOVED = 'members_removed';

    public const string FILES_UPLOADED = 'files_uploaded';

    public const string SUPPLIER_PRODUCT_LINKED = 'supplier_product_linked';

    public const string DESIGN_VERSION_STARTED = 'design_version_started';

    public const string DESIGN_SUBMITTED = 'design_submitted';

    public const string DESIGN_APPROVED = 'design_approved';

    public const string DESIGN_CHANGES_REQUESTED = 'design_changes_requested';

    public const string SIGNED_OFF = 'signed_off';

    public const string REMINDERS_SENT = 'reminders_sent';

    public const string PURCHASE_ORDER_LINKED = 'purchase_order_linked';

    public const string PURCHASE_ORDER_CREATED = 'purchase_order_created';

    public const string PURCHASE_ORDER_UNLINKED = 'purchase_order_unlinked';

    public const string DESIGN_ROUND_SKIPPED = 'design_round_skipped';

    public const string DESIGN_ROUND_UNSKIPPED = 'design_round_unskipped';

    public const string SIGN_OFF_WITHDRAWN = 'sign_off_withdrawn';

    public const string SKU_BRIEF_CHANGED = 'sku_brief_changed';

    public const string FILE_REMOVED = 'file_removed';

    public const string DESIGN_SECTION_ADDED = 'design_section_added';

    public const string DESIGN_SECTION_CHANGED = 'design_section_changed';

    public const string DESIGN_SECTION_REMOVED = 'design_section_removed';

    public const string ARTWORK_UPLOADED = 'artwork_uploaded';

    public const string ARTWORK_SUBMITTED = 'artwork_submitted';

    public const string ARTWORK_APPROVED = 'artwork_approved';

    public const string ARTWORK_CHANGES_REQUIRED = 'artwork_changes_required';

    public const string SAMPLE_JOB_CREATED = 'sample_job_created';

    public const string COMPONENT_ADDED = 'component_added';

    public const string COMPONENT_CHANGED = 'component_changed';

    public const string COMPONENT_REMOVED = 'component_removed';

    public const string COMPONENT_APPROVED = 'component_approved';

    public const string COMPONENT_PURCHASE_CONFIRMED = 'component_purchase_confirmed';

    public const string SAMPLE_STARTED = 'sample_started';

    public const string SAMPLE_READY = 'sample_ready';

    public const string SAMPLE_APPROVED = 'sample_approved';

    public const string SAMPLE_CHANGES_REQUIRED = 'sample_changes_required';

    public const string PACKAGING_SAMPLE_APPROVED = 'packaging_sample_approved';

    public const string PACKAGING_SAMPLE_CHANGES_REQUIRED = 'packaging_sample_changes_required';

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    public static function record(ProductDevelopment $productDevelopment, string $event, array $old, array $new): void
    {
        $productDevelopment->auditEvent     = $event;
        $productDevelopment->isCustomEvent  = true;
        $productDevelopment->auditCustomOld = $old;
        $productDevelopment->auditCustomNew = $new;
        Event::dispatch(new AuditCustom($productDevelopment));
        $productDevelopment->isCustomEvent  = false;
        $productDevelopment->auditCustomOld = $productDevelopment->auditCustomNew = [];
    }
}
