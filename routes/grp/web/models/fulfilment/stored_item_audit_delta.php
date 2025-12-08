<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 00:16:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Fulfilment\StoredItemAuditDelta\DeleteStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\UpdateStoredItemAuditDelta;
use Illuminate\Support\Facades\Route;

Route::post(
    'stored-item-audit/{storedItemAudit:id}/stored-item-audit-delta',
    StoreStoredItemAuditDelta::class
)->name('stored_item_audit.stored_item_audit_delta.store');

Route::patch(
    'stored-item-audit-delta/{storedItemAuditDelta:id}',
    UpdateStoredItemAuditDelta::class
)->name('stored_item_audit_delta.update');

Route::delete(
    'stored-item-audit-delta/{storedItemAuditDelta:id}',
    DeleteStoredItemAuditDelta::class
)->name('stored_item_audit_delta.delete');
