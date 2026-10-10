<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message between procurement and a supplier, agent or partner, by email or chat, routed to
 * whoever it belongs to. Unrouted messages keep null counterpart ids.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $supplier_id
 * @property int|null $org_supplier_id
 * @property int|null $org_agent_id
 * @property int|null $org_partner_id
 * @property int|null $purchase_order_id
 * @property int|null $dispatched_email_id
 * @property SupplierMessageChannelEnum $channel
 * @property string|null $gmail_message_id
 * @property string|null $whatsapp_message_id
 * @property string|null $phone_number
 * @property string|null $delivery_state
 * @property string|null $gmail_thread_id
 * @property string|null $header_message_id
 * @property string|null $header_references
 * @property int|null $user_id
 * @property SupplierMessageDirectionEnum $direction
 * @property SupplierMessageRoutedByEnum|null $routed_by
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
 * @property-read OrgAgent|null $orgAgent
 * @property-read OrgPartner|null $orgPartner
 * @property-read User|null $user
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read DispatchedEmail|null $dispatchedEmail
 */
class SupplierMessage extends Model
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
            'channel'     => SupplierMessageChannelEnum::class,
            'direction'   => SupplierMessageDirectionEnum::class,
            'routed_by'   => SupplierMessageRoutedByEnum::class,
            'to'          => 'array',
            'cc'          => 'array',
            'attachments' => 'array',
            'sent_at'     => 'datetime',
        ];
    }

    /**
     * @return array{supplier_id: int|null, org_supplier_id: int|null, org_agent_id: int|null, org_partner_id: int|null}
     */
    public static function counterpartAttributes(OrgSupplier|OrgAgent|OrgPartner|null $counterpart): array
    {
        return [
            'supplier_id'     => $counterpart instanceof OrgSupplier ? $counterpart->supplier_id : null,
            'org_supplier_id' => $counterpart instanceof OrgSupplier ? $counterpart->id : null,
            'org_agent_id'    => $counterpart instanceof OrgAgent ? $counterpart->id : null,
            'org_partner_id'  => $counterpart instanceof OrgPartner ? $counterpart->id : null,
        ];
    }

    public function counterpart(): OrgSupplier|OrgAgent|OrgPartner|null
    {
        return $this->orgSupplier ?? $this->orgAgent ?? $this->orgPartner;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function orgSupplier(): BelongsTo
    {
        return $this->belongsTo(OrgSupplier::class);
    }

    public function orgAgent(): BelongsTo
    {
        return $this->belongsTo(OrgAgent::class);
    }

    public function orgPartner(): BelongsTo
    {
        return $this->belongsTo(OrgPartner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * A message about an agent order answers every supplier order in it, though only the first is
     * stored on the message.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function linkedPurchaseOrders(): Collection
    {
        $purchaseOrder = $this->purchaseOrder;

        if (! $purchaseOrder) {
            return new Collection();
        }

        if (! $purchaseOrder->isAgentOrder() || ! $purchaseOrder->agent_order_reference) {
            return new Collection([$purchaseOrder]);
        }

        return $purchaseOrder->agentOrderPurchaseOrders()->orderBy('reference')->get();
    }

    public function dispatchedEmail(): BelongsTo
    {
        return $this->belongsTo(DispatchedEmail::class);
    }
}
