<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;

/**
 * Something a supplier sent us from its page: a change to its company details, product pictures, a customer to
 * introduce, a new product idea, a compliance document or a price change request. It waits in the supply chain
 * queue; nothing in aiku changes until staff accept it, and the supplier sees its state and our feedback.
 *
 * @property SupplierSubmissionTypeEnum $type
 * @property SupplierSubmissionStateEnum $state
 * @property array<string, mixed> $data
 */
class SupplierSubmission extends Model implements HasMedia
{
    use InGroup;
    use HasAttachments;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'         => SupplierSubmissionTypeEnum::class,
            'state'        => SupplierSubmissionStateEnum::class,
            'data'         => 'array',
            'applied'      => 'array',
            'expires_on'   => 'date',
            'submitted_at' => 'datetime',
            'decided_at'   => 'datetime',
            'applied_at'   => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function portalContact(): BelongsTo
    {
        return $this->belongsTo(SupplierPortalContact::class, 'supplier_portal_contact_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
