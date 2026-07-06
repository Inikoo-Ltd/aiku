<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $chat_agent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Chat\ChatAgent|null $agent
 * @property-read Organisation $organisation
 * @property-read Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopHasChatAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopHasChatAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShopHasChatAgent query()
 * @mixin \Eloquent
 */
class ShopHasChatAgent extends Model
{
    use HasFactory;
    protected $table = 'shop_has_chat_agents';

    protected $fillable = [
        'organisation_id',
        'shop_id',
        'chat_agent_id',
    ];

    protected $casts = [
        'shop_id' => 'integer',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class, 'chat_agent_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
