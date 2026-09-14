<?php

namespace App\Models\Comms;

use App\Enums\Comms\WhatsappSubscriber\WhatsappSubscriberOptInMethodEnum;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property WhatsappSubscriberOptInMethodEnum $opt_in_method
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read Model|\Eloquent|null $parent
 */
class WhatsappSubscriber extends Model
{
    use SoftDeletes;
    use InShop;

    protected $table = 'whatsapp_subscribers';

    protected $guarded = [];

    protected $casts = [
        'opt_in_method' => WhatsappSubscriberOptInMethodEnum::class,
    ];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
