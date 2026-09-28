<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Enums\Procurement\SupplierEmail\SupplierEmailRoutedByEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\SupplyChain\Supplier;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email between the procurement mailbox and the outside world, mirrored from Gmail and
 * routed to the supplier it belongs to. Unrouted mail keeps null supplier ids.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $supplier_id
 * @property int|null $org_supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $dispatched_email_id
 * @property string|null $gmail_message_id
 * @property string|null $gmail_thread_id
 * @property SupplierEmailDirectionEnum $direction
 * @property SupplierEmailRoutedByEnum|null $routed_by
 * @property string|null $from_address
 * @property string|null $from_name
 * @property array<int, array{name: ?string, address: string}> $to
 * @property array<int, array{name: ?string, address: string}> $cc
 * @property string|null $subject
 * @property string|null $snippet
 * @property string|null $body_text
 * @property string|null $body_html
 * @property array<int, array{attachment_id: string, name: string, mime_type: string, size: int}> $attachments
 * @property \Illuminate\Support\Carbon $sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Supplier|null $supplier
 * @property-read OrgSupplier|null $orgSupplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read DispatchedEmail|null $dispatchedEmail
 */
class SupplierEmail extends Model
{
    use InOrganisation;

    protected $guarded = [];

    protected $attributes = [
        'to'          => '[]',
        'cc'          => '[]',
        'attachments' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'direction'   => SupplierEmailDirectionEnum::class,
            'routed_by'   => SupplierEmailRoutedByEnum::class,
            'to'          => 'array',
            'cc'          => 'array',
            'attachments' => 'array',
            'sent_at'     => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orgSupplier(): BelongsTo
    {
        return $this->belongsTo(OrgSupplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function dispatchedEmail(): BelongsTo
    {
        return $this->belongsTo(DispatchedEmail::class);
    }
}
