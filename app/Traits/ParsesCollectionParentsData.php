<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 21:03:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Refactor by Junie (JetBrains AI)
 * Created: Wed, 29 Oct 2025
 */

namespace App\Traits;

trait ParsesCollectionParentsData
{
    /**
     * Parse the packed parents data string into an array structure.
     *
     * Expected input format: "slugs|types|codes|names" where each section is a comma-separated list.
     */
    protected function parseCollectionParentsData(?string $parentsData): array
    {
        $parents = [];
        if ($parentsData === '|||' || $parentsData === null) {
            return $parents;
        }

        [$slugsData, $typesData, $codesData, $namesData] = explode('|', $parentsData);
        $slugs = $slugsData !== '' ? explode(',', $slugsData) : [];
        $types = $typesData !== '' ? explode(',', $typesData) : [];
        $codes = $codesData !== '' ? explode(',', $codesData) : [];
        $names = $namesData !== '' ? explode(',', $namesData) : [];

        foreach ($slugs as $key => $slug) {
            $parents[] = [
                'slug' => $slug,
                'type' => $types[$key] ?? null,
                'code' => $codes[$key] ?? null,
                'name' => $names[$key] ?? null,
            ];
        }

        return $parents;
    }
}
