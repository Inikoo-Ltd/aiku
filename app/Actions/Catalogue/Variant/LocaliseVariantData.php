<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Variant;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class LocaliseVariantData
{
    use AsObject;

    /**
     * The English axis labels and option names of a variant, e.g. ["Plug", "UK Plug", "EU Plug"].
     *
     * @param array<string, mixed>|null $variantData
     * @return list<string>
     */
    public function terms(?array $variantData): array
    {
        return collect(data_get($variantData, 'variants', []))
            ->flatMap(fn (array $axis) => [$axis['label'] ?? null, ...($axis['options'] ?? [])])
            ->filter(fn ($term) => is_string($term) && $term !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Rewrites axis labels and option names, and the product entries keyed by them, in the shop's language,
     * so the website shows "Wtyczka: Wtyczka UE" while still matching each product to its options.
     *
     * @param array<string, mixed>|null $variantData
     * @param array<string, string>|null $translations
     * @return array<string, mixed>|null
     */
    public function handle(?array $variantData, ?array $translations): ?array
    {
        $translations = array_filter($translations ?? [], fn ($translation) => filled($translation));
        if (!$variantData || !$translations) {
            return $variantData;
        }

        $translate = fn ($term) => is_string($term) ? ($translations[$term] ?? $term) : $term;
        $axes      = collect(data_get($variantData, 'variants', []))->pluck('label')->filter()->all();

        $variantData['variants'] = collect(data_get($variantData, 'variants', []))
            ->map(fn (array $axis) => array_merge($axis, [
                'label'   => $translate($axis['label'] ?? null),
                'options' => array_map($translate, $axis['options'] ?? []),
            ]))
            ->all();

        if (Arr::has($variantData, 'products')) {
            $variantData['products'] = collect($variantData['products'])
                ->map(function ($entry) use ($axes, $translate) {
                    foreach ($axes as $axis) {
                        if (array_key_exists($axis, $entry)) {
                            $value = Arr::pull($entry, $axis);
                            $entry[$translate($axis)] = $translate($value);
                        }
                    }

                    return $entry;
                })
                ->all();
        }

        return $variantData;
    }
}
