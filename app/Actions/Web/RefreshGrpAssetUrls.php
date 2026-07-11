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
        $json = preg_replace_callback(
            '#/grp/assets/([A-Za-z0-9_.-]+)-[A-Za-z0-9_-]{8}\.(png|svg|jpe?g|webp|gif)#',
            function (array $matches) {
                $entry = $this->grpManifest()["resources/art/payment_service_providers/$matches[1].$matches[2]"] ?? [];
                $file  = $entry['file'] ?? null;

                return $file ? "/grp/$file" : $matches[0];
            },
            json_encode($data, JSON_UNESCAPED_SLASHES)
        );

        return json_decode($json, true) ?? $data;
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
