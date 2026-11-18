<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use App\Models\Helpers\Media;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $meta_channel_id
 * @property string $template_id
 * @property string $name
 * @property string|null $parameter_format
 * @property string|null $language
 * @property string|null $status
 * @property string|null $category
 * @property bool $disable_ios_autofill
 * @property bool $is_primary_device_delivery_only
 * @property \Illuminate\Support\Carbon|null $synchronize_at
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WhatsappOutbox> $whatsappOutboxes
 * @method static Builder<static>|MetaMessageTemplate withoutWhatsappOutboxTemplates()
 */
class MetaMessageTemplate extends Model implements HasMedia
{
    use SoftDeletes;
    use HasImage;
    use InteractsWithMedia;

    protected $table = 'meta_message_templates';

    protected $guarded = [];

    protected $casts = [
        'data'                            => 'array',
        'disable_ios_autofill'            => 'boolean',
        'is_primary_device_delivery_only' => 'boolean',
        'synchronize_at'                  => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }
    public function headerMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'header_media_id');
    }

    /**
     * The address a browser can load the header image from. The media disk is not web
     * served, so it goes through the image proxy; a video or document header has none.
     */
    public function headerImageUrl(): ?string
    {
        if (!str_starts_with((string) $this->headerMedia?->mime_type, 'image/')) {
            return null;
        }

        return Arr::get($this->imageSources(0, 0, 'headerMedia'), 'original');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function whatsappOutboxes(): HasMany
    {
        return $this->hasMany(WhatsappOutbox::class);
    }

    /**
     * A template chosen for a WhatsApp outbox is the wording of that notification. Sent by
     * hand from a chat it would tell the customer an order was confirmed or dispatched when
     * nothing happened, so the chat leaves it out.
     */
    public function isUsedByWhatsappOutbox(): bool
    {
        return $this->whatsappOutboxes()->exists();
    }

    public function scopeWithoutWhatsappOutboxTemplates(Builder $query): Builder
    {
        return $query->whereDoesntHave('whatsappOutboxes');
    }
}
