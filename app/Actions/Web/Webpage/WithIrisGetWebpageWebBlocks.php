<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

trait WithIrisGetWebpageWebBlocks
{
    use WithFillIrisWebBlocks;

    public function getIrisWebBlocks(Webpage $webpage, array $webBlocks, bool $isLoggedIn): array
    {
        return $this->getParsedWebBlocks($webpage, $webBlocks, isLoggedIn: $isLoggedIn);
    }


    public function getParsedWebBlocks(Webpage $webpage, array $webBlocks, bool $isLoggedIn)
    {
        $parsedWebBlocks = [];

        /** @var WebBlock $webBlock */
        foreach ($webBlocks as $key => $webBlock) {
            if (!Arr::get($webBlock, 'show')) {
                continue;
            }

            if ($isLoggedIn && !Arr::get($webBlock, 'visibility.in')) {
                continue;
            }

            if (!$isLoggedIn && !Arr::get($webBlock, 'visibility.out')) {
                continue;
            }

            $parsedWebBlocks = $this->fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock);

            $reveal = $this->getWebBlockReveal(Arr::get($webBlock, 'web_block.layout.reveal'));

            if ($reveal && Arr::exists($parsedWebBlocks, $key)) {
                data_set($parsedWebBlocks[$key], 'reveal', $reveal);
            }
        }

        return $parsedWebBlocks;
    }

    /**
     * Blocks rebuilt by the GetIrisWebBlock* actions keep only type and structure,
     * so their reveal-on-click setting is read from the original block and put back.
     *
     * @return array{key: string, close_label: string|null, scroll_to: bool}|null
     */
    private function getWebBlockReveal(object|array|null $reveal): ?array
    {
        $reveal = (array) $reveal;

        if (!Arr::get($reveal, 'enabled') || !Arr::get($reveal, 'key')) {
            return null;
        }

        return [
            'key'         => Arr::get($reveal, 'key'),
            'close_label' => Arr::get($reveal, 'close_label') ?: null,
            'scroll_to'   => (bool) Arr::get($reveal, 'scroll_to', true),
        ];
    }

}
