<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Models\Comms;

use App\Actions\Utils\Abbreviate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * The WhatsApp channel of a customer notification. It shares the event code with the email
 * outbox of the same name, so one event can send both, but it is switched on and off and
 * given its template on its own.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property string $slug
 * @property OutboxCodeEnum $code
 * @property string $name
 * @property OutboxStateEnum $state
 * @property int|null $meta_message_template_id
 * @property int|null $interval Hours between two bulk runs
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read MetaMessageTemplate|null $metaMessageTemplate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MetaChatMessage> $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappBulkRun> $bulkRuns
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\SysAdmin\Group $group
 */
class WhatsappOutbox extends Model
{
    use SoftDeletes;
    use HasSlug;
    use InShop;

    protected $table = 'whatsapp_outboxes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'code'         => OutboxCodeEnum::class,
            'state'        => OutboxStateEnum::class,
            'last_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn () => 'wa '.Abbreviate::run(string: $this->code->value, maximumLength: 16).' '.$this->shop->slug)
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function metaMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(MetaMessageTemplate::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MetaChatMessage::class);
    }

    public function bulkRuns(): HasMany
    {
        return $this->hasMany(WhatsappBulkRun::class);
    }

    /**
     * These go to many customers at once from a scheduled run rather than one at a time from
     * the event that happened to an order.
     */
    public function isBulk(): bool
    {
        return in_array($this->code, [OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION, OutboxCodeEnum::OOS_NOTIFICATION], true);
    }
}
