<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use App\Models\SysAdmin\User;
use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A private link that shows one supplier how its products sell and its purchase orders, without an account.
 * Only the hash of the token is kept, so the link is shown once, when it is made.
 */
class SupplierShareLink extends Model
{
    use InGroup;

    protected $casts = [
        'code_expires_at' => 'datetime',
        'expires_at'      => 'datetime',
        'revoked_at'      => 'datetime',
        'last_viewed_at'  => 'datetime',
    ];

    protected $guarded = [];

    protected $hidden = ['token_hash', 'code_hash'];

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The live link a token opens; anything else is not found.
     */
    public static function findLiveByToken(string $token): self
    {
        $link = self::live()->where('token_hash', self::hashToken($token))->with('supplier')->first();
        abort_if(!$link, 404);

        return $link;
    }

    public function sessionKey(): string
    {
        return 'supplier_share_verified.'.$this->id;
    }

    public function needsCode(): bool
    {
        return $this->email && !session()->get($this->sessionKey());
    }

    public function maskedEmail(): ?string
    {
        if (!$this->email) {
            return null;
        }

        [$name, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return mb_substr($name, 0, 1).str_repeat('*', max(2, mb_strlen($name) - 1)).'@'.$domain;
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return !$this->revoked_at && $this->expires_at->isFuture();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function portalContact(): BelongsTo
    {
        return $this->belongsTo(SupplierPortalContact::class, 'supplier_portal_contact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(SupplierShareLinkView::class);
    }
}
