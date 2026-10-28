<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\SysAdmin\User;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An email staff allowed to ask for the supplier's private link. Typing it on the supplier sign-in page emails a
 * fresh link to that address and nowhere else; a contact staff did not add, or one revoked, gets nothing.
 */
class SupplierPortalContact extends Model
{
    use InGroup;

    protected $casts = [
        'revoked_at'        => 'datetime',
        'last_link_sent_at' => 'datetime',
    ];

    protected $guarded = [];

    public function scopeAuthorised(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by_user_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(SupplierShareLink::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(SupplierPortalProfile::class);
    }
}
