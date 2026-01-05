<?php

namespace App\Models\CRM\Livechat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
