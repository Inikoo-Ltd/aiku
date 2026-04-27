<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Oct 2023 23:29:42 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use BadMethodCallException;

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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit query()
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
            function (Audit $audit): bool {
                if ($audit->tags) {
                    $tags = array_values(array_filter(array_map('trim', explode(',', $audit->tags)), fn (string $tag) => $tag !== ''));
                    $audit->tags = json_encode($tags);
                } else {
                    $audit->tags = '[]';
                }
                if ($audit->event === 'updated') {
                    $recentAudit = self::where('auditable_type', $audit->auditable_type)
                        ->where('auditable_id', $audit->auditable_id)
                        ->where('event', 'updated')
                        ->where('user_type', $audit->user_type)
                        ->where('user_id', $audit->user_id)
                        ->where('created_at', '>=', now()->subSeconds(3))
                        ->latest('id')
                        ->first();

                    if ($recentAudit) {
                        $oldValues = $recentAudit->old_values ?? [];
                        $newValues = $recentAudit->new_values ?? [];
                        $incomingOldValues = is_array($audit->old_values) ? $audit->old_values : [];
                        $incomingNewValues = is_array($audit->new_values) ? $audit->new_values : [];

                        foreach ($incomingNewValues as $key => $newValue) {
                            if (!array_key_exists($key, $oldValues)) {
                                $oldValues[$key] = $incomingOldValues[$key] ?? null;
                            }
                            $newValues[$key] = $newValue;

                            if ($oldValues[$key] == $newValues[$key]) {
                                unset($oldValues[$key], $newValues[$key]);
                            }
                        }

                        if (!empty($newValues)) {
                            static::withoutEvents(function () use ($recentAudit, $oldValues, $newValues) {
                                $recentAudit->update([
                                    'old_values' => $oldValues,
                                    'new_values' => $newValues,
                                ]);
                            });
                        } else {
                            static::withoutEvents(fn () => $recentAudit->delete());
                        }

                        return false;
                    }
                }

                return true;
            }
        );
    }

    public function auditable(): MorphTo
    {
        $morph = $this->morphTo();

        try {
            return $morph->withTrashed();
        } catch (BadMethodCallException) {
            return $morph;
        }
    }

}
