<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use App\Enums\Procurement\SupplierWechatConversation\SupplierWechatConversationKindEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use App\Models\Traits\InTicketProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One WeChat chat between procurement staff on WeCom and someone outside the company: a
 * supplier talking to one member of staff, or a group chat. Read from the WeCom chat archive,
 * so it cannot be written to from Aiku.
 *
 * @property int $id
 * @property string $ulid
 * @property int $group_id
 * @property int $organisation_id
 * @property SupplierWechatConversationKindEnum $kind
 * @property string|null $room_id
 * @property string|null $staff_wecom_userid
 * @property string|null $external_userid
 * @property string|null $name
 * @property string|null $remark
 * @property string|null $corp_name
 * @property int|null $supplier_id
 * @property int|null $org_supplier_id
 * @property int|null $org_agent_id
 * @property int|null $org_partner_id
 * @property int|null $ticket_project_id
 * @property int|null $ticket_project_milestone_id
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property string|null $last_message_snippet
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SupplierWechatConversation extends Model
{
    use InOrganisation;
    use InTicketProject;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind'            => SupplierWechatConversationKindEnum::class,
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierWechatConversation $conversation) {
            $conversation->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The remark staff gave the contact in WeCom names the supplier best; its WeChat name and
     * then the raw id are what is left before anyone has done that.
     */
    public function displayName(): string
    {
        if ($this->kind === SupplierWechatConversationKindEnum::GROUP) {
            return $this->name ?: __('WeChat group');
        }

        return $this->remark ?: ($this->name ?: (string) $this->external_userid);
    }

    public function counterpart(): OrgSupplier|OrgAgent|OrgPartner|null
    {
        return $this->orgSupplier ?? $this->orgAgent ?? $this->orgPartner;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupplierMessage::class);
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'supplier_wechat_conversation_reads')->withPivot('last_read_at');
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

    public function canBeViewedBy(User $user): bool
    {
        return $user->authTo("procurement.$this->organisation_id.view");
    }
}
