<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Oct 2023 23:29:42 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Helpers\Audit
 *
 * @property int $id
 * @property int|null $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int|null $website_id
 * @property int|null $customer_id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string $tags
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $event
 * @property string|null $comments
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property array<array-key, mixed>|null $data
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $auditable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit query()
 *
 * @mixin \Eloquent
 */
class Audit extends \OwenIt\Auditing\Models\Audit
{
    protected function casts(): array
    {
        return [
            'data' => 'json',
        ];
    }

    protected $attributes = [
        'data' => '{}',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (Audit $audit) {
                if ($audit->tags) {
                    $audit->tags = json_encode(explode(',', $audit->tags));
                } else {
                    $audit->tags = '[]';
                }
            }
        );
    }

    public function auditable(): MorphTo
    {
        $morph = $this->morphTo();

        try {
            return $morph->withTrashed();
        } catch (\Exception $e) {
            return $morph;
        }
    }
}
