<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
 */
class MetaMessageTemplate extends Model
{
    use SoftDeletes;

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

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
