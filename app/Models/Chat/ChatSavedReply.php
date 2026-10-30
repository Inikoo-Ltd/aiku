<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026 14:12:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reply staff keep to paste into a chat or email. With a user it is that person's own and
 * follows them to every shop; without one it is shared with whoever answers the shop's chats,
 * or every shop of the organisation when it has no shop.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $user_id
 * @property int|null $created_by_user_id
 * @property string $title
 * @property string $body
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ChatSavedReply extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function isPersonal(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * @param  Builder<ChatSavedReply>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user, Shop $shop): void
    {
        $query->where(function (Builder $query) use ($user, $shop) {
            $query->where('user_id', $user->id)
                ->orWhere(function (Builder $shared) use ($shop) {
                    $shared->whereNull('user_id')
                        ->where('organisation_id', $shop->organisation_id)
                        ->where(fn (Builder $scope) => $scope->whereNull('shop_id')->orWhere('shop_id', $shop->id));
                });
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
