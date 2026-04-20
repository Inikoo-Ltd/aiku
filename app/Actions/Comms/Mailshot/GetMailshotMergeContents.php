<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 1 Apr 2026 14:33:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Product\Json\GetNewestProducts;
use App\Enums\Comms\Mailshot\MailshotMergeContentsEnum;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetMailshotMergeContents
{
    use AsAction;
    use WithAttributes;


    public function handle(?Shop $shop = null): array
    {
        $contents = MailshotMergeContentsEnum::contents();

        // Fetch newest products if shop is provided
        if ($shop) {
            $newestProducts = GetNewestProducts::run($shop, 5);

            // Find and update the New In Products merge content with nested structure
            foreach ($contents as &$content) {
                if ($content['value'] === '[NewInProducts]') {
                    $content['items'] = $newestProducts->toArray();
                }
            }
        }

        return $contents;
    }

    public function asController(): array
    {
        return $this->handle();
    }
}
