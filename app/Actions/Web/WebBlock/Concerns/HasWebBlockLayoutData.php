<?php

/*
 * author Louis Perez
 * created on 09-06-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Models\Web\Webpage;

trait HasWebBlockLayoutData
{
    protected function setWebBlockLayoutData(Webpage $webpage, array &$webBlock, string $layoutKey, array $permissions = ['edit', 'hidden']): void
    {
        $webBlockType = data_get($webBlock, 'type', '');
        $webPublishedLayout = $webpage->website->published_layout;

        $webBlockData = $this->getWebBlockLayoutData($webPublishedLayout, $webBlockType, $layoutKey);

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', $webBlockData);
        data_set($webBlock, 'web_block.layout.data.fieldValue.id', data_get($webBlock, 'type'));
    }

    protected function getWebBlockLayoutData(array $webPublishedLayout, string $webBlockType, string $layoutKey): array
    {
        return data_get($webPublishedLayout, "$layoutKey.$webBlockType.fieldValue", []);
    }
}
