<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession\Concerns;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;

trait WithChatSlackSettings
{
    /**
     * @return array{token: string|null, destinations: array<int, array{type: string, id: string, name: string}>}
     */
    protected function shopSlackSettings(?Shop $shop): array
    {
        $settings = $shop?->settings ?? [];

        return [
            'token'        => Arr::get($settings, 'chat.slack_token'),
            'destinations' => (array) Arr::get($settings, 'chat.slack_destinations', []),
        ];
    }
}
