<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Jul 2026 02:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web;

use Lorisleiva\Actions\Concerns\AsObject;

class RefreshGrpAssetUrls
{
    use AsObject;

    /**
     * Workshop-saved layouts store fully hashed /grp/assets urls from whichever build
     * was live when they were saved; remap them to the current build's files.
     */
    public function handle(array $data): array
    {
        return $this->walk($data);
    }

    /**
     * Only strings and plain arrays are touched: block data may hold live resource
     * objects whose serialization (pagination meta) must stay with Inertia.
     */
    private function walk(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_contains($value, '/grp/assets/') ? $this->remapUrl($value) : $value;
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->walk($item), $value);
        }

        return $value;
    }

    private function remapUrl(string $value): string
    {
        return preg_replace_callback(
            '#/grp/assets/([A-Za-z0-9_.-]+)-[A-Za-z0-9_-]{8}\.(png|svg|jpe?g|webp|gif)#',
            function (array $matches) {
                $entry = $this->grpManifest()["resources/art/payment_service_providers/$matches[1].$matches[2]"] ?? [];
                $file  = $entry['file'] ?? null;

                return $file ? "/grp/$file" : $matches[0];
            },
            $value
        );
    }

    private function grpManifest(): array
    {
        static $manifest = null;

        if ($manifest === null) {
            $manifest = rescue(
                fn () => json_decode(file_get_contents(public_path('grp/manifest.json')), true) ?? [],
                [],
                false
            );
        }

        return $manifest;
    }
}
