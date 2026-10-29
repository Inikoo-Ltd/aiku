<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Somebody the inbox writes to who is not a customer: a carrier such as DPD or GLS, the
 * accountants, a supplier. Saved so the next new email to them is picked from a list instead
 * of the address being looked up again. A contact with no shop is offered on every shop of
 * the organisation.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $name
 * @property string $email
 * @property string|null $company
 * @property string|null $note
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read Shop|null $shop
 * @property-read User|null $createdBy
 * @method static Builder<static>|ChatEmailContact offeredOnShop(Shop $shop)
 * @mixin \Eloquent
 */
class ChatEmailContact extends Model
{
    protected $table = 'chat_email_contacts';

    protected $guarded = [];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeOfferedOnShop(Builder $query, Shop $shop): Builder
    {
        return $query->where('organisation_id', $shop->organisation_id)
            ->where(fn (Builder $query) => $query->where('shop_id', $shop->id)->orWhereNull('shop_id'));
    }
}
