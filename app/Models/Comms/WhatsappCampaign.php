<?php

namespace App\Models\Comms;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignTypeEnum;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\User;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string $name
 * @property int|null $meta_message_template_id
 * @property WhatsappCampaignStateEnum $state
 * @property WhatsappCampaignTypeEnum $type
 * @property \Illuminate\Support\Carbon|null $ready_at
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $start_sending_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $stopped_at
 * @property array|null $recipients_recipe
 * @property array|null $recipients_list
 * @property int $recipients_count
 * @property int|null $publisher_id
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read MetaMessageTemplate|null $metaMessageTemplate
 * @property-read User|null $publisher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappRecipient> $recipients
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappDeliveryChannel> $deliveryChannels
 */
class WhatsappCampaign extends Model
{
    use SoftDeletes;
    use InShop;
    use HasSlug;

    protected $table = 'whatsapp_campaigns';

    protected $guarded = [];

    protected $casts = [
        'state'             => WhatsappCampaignStateEnum::class,
        'type'              => WhatsappCampaignTypeEnum::class,
        'recipients_recipe' => 'array',
        'recipients_list'   => 'array',
        'data'              => 'array',
        'ready_at'          => 'datetime',
        'scheduled_at'      => 'datetime',
        'start_sending_at'  => 'datetime',
        'sent_at'           => 'datetime',
        'cancelled_at'      => 'datetime',
        'stopped_at'        => 'datetime',
    ];

    protected $attributes = [
        'data'              => '{}',
        'recipients_recipe' => '{}',
        'recipients_list'   => '[]',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function metaMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(MetaMessageTemplate::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappRecipient::class);
    }

    public function deliveryChannels(): HasMany
    {
        return $this->hasMany(WhatsappDeliveryChannel::class);
    }
}
