<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 12:46:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebsiteVisitor;

use DeviceDetector\Parser\Bot as BotParser;
use Exception;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class IsBot
{
    use AsObject;

    public function handle(string $userAgent): bool
    {
        $cacheKey = 'bot_detection_v1:'.md5($userAgent);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }


        $botParser = new BotParser();
        $botParser->setUserAgent($userAgent);
        $botParser->discardDetails();
        try {
            $result = $botParser->parse();
            if (is_null($result)) {
                $isBot = false;
            } else {
                $isBot = true;
            }

            Cache::put($cacheKey, $isBot, 3600);
        } catch (Exception) {
            $isBot = true;
        }

        return $isBot;
    }

}
