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
            function (Audit $audit) {
                if ($audit->tags) {
                    $audit->tags = json_encode(explode(",", $audit->tags));
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

                        foreach ($audit->new_values as $key => $newValue) {
                            if (!array_key_exists($key, $oldValues)) {
                                $oldValues[$key] = $audit->old_values[$key] ?? null;
                            }
                            $newValues[$key] = $newValue;

                            // Important: loose comparison '==' might be safer for numbers formatted differently,
                            // but strict '===' prevents accidental type-juggling bugs. But wait, array values from JSON
                            // could be strings or floats. Let's use loose comparison to handle "1" vs 1 correctly
                            // exactly how auditing expects.
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

                        $audit->old_values = $oldValues; // Give it back so we don't return false for a completely dead audit, wait we return false anyway so it's not saved.

                        return false;
                    }
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
