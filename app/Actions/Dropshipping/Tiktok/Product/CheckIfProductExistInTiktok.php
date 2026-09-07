<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CheckIfProductExistInTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * The TikTok reply for the listing this portfolio points at, or the trait's error envelope.
     *
     * @return array<string, mixed>
     */
    public function handle(TiktokUser $tiktokUser, Portfolio $portfolio): array
    {
        if (blank($portfolio->platform_product_id)) {
            return ['error' => true, 'data' => 'Portfolio has no TikTok product id'];
        }

        return $tiktokUser->getProduct($portfolio->platform_product_id);
    }
}
